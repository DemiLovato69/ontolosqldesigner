<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ShareSettingsDTO;
use App\Enums\DiagramAccess;
use App\Enums\ImportStatus;
use App\Enums\VisitorStatus;
use App\Events\VisitorAccessChanged;
use App\Events\VisitorRequested;
use App\Models\Diagram;
use App\Models\DiagramInvite;
use App\Models\DiagramVisitor;
use App\Models\User;
use App\Support\DiagramSchema;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class DiagramSharingService
{
    /**
     * Ensure the diagram is shared (defaults to 'read' if not already set).
     *
     * @return string The current share_access value.
     */
    public function ensureShared(Diagram $diagram): string
    {
        if (! $diagram->share_access) {
            $diagram->share_access = DiagramAccess::READ;
            $diagram->save();
        }

        return ($diagram->share_access ?? DiagramAccess::READ)->value;
    }

    /**
     * Remove sharing and library visibility from the diagram.
     */
    public function unshare(Diagram $diagram): void
    {
        $diagram->share_access = null;
        $diagram->library = false;
        $diagram->save();
    }

    /** @return array{share_access: string|null, require_approval: bool, library: bool} */
    public function updateShareSettings(Diagram $diagram, ShareSettingsDTO $dto): array
    {
        if ($dto->access !== null) {
            $diagram->share_access = $dto->access;
        }

        if ($dto->requireApproval !== null) {
            $diagram->require_approval = $dto->requireApproval;
        }

        if ($dto->library !== null) {
            $diagram->library = $dto->library;
        }

        if ($diagram->library) {
            $diagram->require_approval = false;
            if ($diagram->share_access !== DiagramAccess::WRITE) {
                $diagram->share_access = DiagramAccess::READ;
            }
        }

        $diagram->save();

        return [
            'share_access' => $diagram->share_access?->value,
            'require_approval' => (bool) $diagram->require_approval,
            'library' => (bool) $diagram->library,
        ];
    }

    /**
     * Return all visitors for a diagram, newest first.
     *
     * @return Collection<int, DiagramVisitor>
     */
    public function getVisitors(Diagram $diagram): Collection
    {
        return $diagram->visitors()->with('user')->orderByDesc('created_at')->get();
    }

    /**
     * Approve a visitor and broadcast the access change.
     */
    public function approveVisitor(Diagram $diagram, DiagramVisitor $visitor): DiagramVisitor
    {
        $visitor->status = VisitorStatus::APPROVED;
        if ($diagram->share_access === DiagramAccess::PER_USER) {
            $visitor->access = $visitor->access ?? DiagramAccess::READ;
        }
        $visitor->save();

        try {
            $access = $diagram->share_access === DiagramAccess::PER_USER
                ? ($visitor->access ?? DiagramAccess::READ)
                : ($diagram->share_access ?? DiagramAccess::READ);
            broadcast(new VisitorAccessChanged($visitor->user_id, $diagram->share_token, $access));
        } catch (Exception) {
        }

        return $visitor;
    }

    /**
     * Set a visitor's access level (or revoke it) and broadcast the change.
     */
    public function setVisitorAccess(Diagram $diagram, DiagramVisitor $visitor, DiagramAccess $access): DiagramVisitor
    {
        if ($access === DiagramAccess::REVOKED) {
            $visitor->status = VisitorStatus::REVOKED;
            $visitor->access = null;
        } else {
            $visitor->status = VisitorStatus::APPROVED;
            $visitor->access = $access;
        }
        $visitor->save();

        try {
            $broadcastAccess = $visitor->status === VisitorStatus::REVOKED
                ? DiagramAccess::REVOKED
                : ($visitor->access ?? $diagram->share_access ?? DiagramAccess::READ);
            broadcast(new VisitorAccessChanged($visitor->user_id, $diagram->share_token, $broadcastAccess));
        } catch (Exception) {
        }

        return $visitor;
    }

    /**
     * Save a schema update from a shared user if they have write access.
     *
     * @param array<int, mixed> $schema
     * @param list<array<string, mixed>>|null $valueTypes
     */
    public function saveByToken(Diagram $diagram, User $user, array $schema, ?array $valueTypes = null): bool
    {
        if (! $this->hasWriteAccess($diagram, $user)) {
            return false;
        }

        $diagram->schema = DiagramSchema::withoutRuntimeState($schema);
        if ($valueTypes !== null) {
            $diagram->value_types = $valueTypes;
        }
        if (! in_array(
            $diagram->import_status,
            [ImportStatus::PENDING, ImportStatus::PROCESSING],
            true
        )) {
            $diagram->import_status = null;
            $diagram->import_error = null;
            $diagram->import_warnings = null;
        }
        $diagram->save();

        return true;
    }

    /**
     * Resolve the shared access for a user and return the diagram if permitted.
     *
     * @return array{status: string, diagram?: Diagram}
     */
    public function resolveSharedAccess(Diagram $diagram, User $user): array
    {
        if ($user->id === $diagram->user_id) {
            if ($diagram->library) {
                $this->applyPublicLibraryAccess($diagram);
            }

            return ['status' => 'ok', 'diagram' => $diagram];
        }

        if (! $diagram->share_access) {
            return ['status' => 'not_shared'];
        }

        $invite = $this->inviteForUser($diagram, $user);
        if ($invite) {
            $diagram->share_access = $invite->access ?? DiagramAccess::READ;

            return ['status' => 'ok', 'diagram' => $diagram];
        }

        if ($diagram->library) {
            $visitor = DiagramVisitor::where('diagram_id', $diagram->id)
                ->where('user_id', $user->id)
                ->first();

            if ($visitor?->status === VisitorStatus::REVOKED) {
                return ['status' => 'revoked'];
            }

            $this->applyPublicLibraryAccess($diagram);

            return ['status' => 'ok', 'diagram' => $diagram];
        }

        $defaultStatus = $diagram->require_approval ? VisitorStatus::PENDING : VisitorStatus::APPROVED;
        $isPerUser = $diagram->share_access === DiagramAccess::PER_USER;

        $visitor = DiagramVisitor::firstOrCreate(
            ['diagram_id' => $diagram->id, 'user_id' => $user->id],
            $isPerUser ? ['status' => $defaultStatus, 'access' => DiagramAccess::READ] : ['status' => $defaultStatus]
        );

        if ($visitor->wasRecentlyCreated && $diagram->require_approval) {
            try {
                broadcast(new VisitorRequested($diagram->share_token));
            } catch (Exception) {
            }
        }

        if ($visitor->status === VisitorStatus::REVOKED) {
            return ['status' => 'revoked'];
        }

        if ($isPerUser) {
            if ($visitor->status !== VisitorStatus::APPROVED) {
                return ['status' => 'pending'];
            }
            $diagram->share_access = $visitor->access ?? DiagramAccess::READ;
        } else {
            if ($diagram->require_approval && $visitor->status !== VisitorStatus::APPROVED) {
                return ['status' => 'pending'];
            }
        }

        return ['status' => 'ok', 'diagram' => $diagram];
    }

    private function hasWriteAccess(Diagram $diagram, User $user): bool
    {
        $invite = $this->inviteForUser($diagram, $user);
        if ($invite) {
            return $invite->access === DiagramAccess::WRITE;
        }

        if ($diagram->library && $diagram->share_access === DiagramAccess::WRITE) {
            return ! DiagramVisitor::where('diagram_id', $diagram->id)
                ->where('user_id', $user->id)
                ->where('status', VisitorStatus::REVOKED)
                ->exists();
        }

        if ($diagram->share_access === DiagramAccess::PER_USER) {
            return DiagramVisitor::where('diagram_id', $diagram->id)
                ->where('user_id', $user->id)
                ->where('status', VisitorStatus::APPROVED)
                ->where('access', DiagramAccess::WRITE)
                ->exists();
        }

        if ($diagram->share_access === DiagramAccess::WRITE) {
            $visitor = DiagramVisitor::where('diagram_id', $diagram->id)
                ->where('user_id', $user->id)
                ->first();

            if ($visitor?->status === VisitorStatus::REVOKED) {
                return false;
            }
            if ($diagram->require_approval) {
                return $visitor?->status === VisitorStatus::APPROVED;
            }

            return true;
        }

        return false;
    }

    private function applyPublicLibraryAccess(Diagram $diagram): void
    {
        $diagram->share_access = $diagram->share_access === DiagramAccess::WRITE
            ? DiagramAccess::WRITE
            : DiagramAccess::READ;
        $diagram->require_approval = false;

        if ($diagram->isDirty(['share_access', 'require_approval'])) {
            $diagram->saveQuietly();
        }
    }

    private function inviteForUser(Diagram $diagram, User $user): ?DiagramInvite
    {
        return DiagramInvite::where('diagram_id', $diagram->id)
            ->where('email', strtolower($user->email))
            ->first();
    }
}
