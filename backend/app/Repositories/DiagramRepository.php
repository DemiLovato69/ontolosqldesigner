<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Enums\ImportStatus;
use App\Enums\VisitorStatus;
use App\Models\Diagram;
use App\Models\User;
use App\Support\DiagramSchema;
use Illuminate\Database\Eloquent\Collection;

class DiagramRepository implements DiagramRepositoryInterface
{
    /** @return Collection<int, Diagram> */
    public function all(User $user): Collection
    {
        return $user->diagrams()
            ->select($this->summaryColumns())
            ->get();
    }

    /** @return array{owned: Collection<int, Diagram>, shared: Collection<int, Diagram>, public: Collection<int, Diagram>} */
    public function dashboard(User $user): array
    {
        $owned = $this->all($user);

        $shared = Diagram::query()
            ->with([
                'user:id,email',
                'visitors' => fn ($query) => $query->where('user_id', $user->id),
                'invites' => fn ($query) => $query->where('email', strtolower($user->email)),
            ])
            ->select($this->summaryColumns())
            ->where('user_id', '!=', $user->id)
            ->where(function ($query) use ($user) {
                $query->whereHas('visitors', function ($visitorQuery) use ($user) {
                    $visitorQuery->where('user_id', $user->id)
                        ->where('status', VisitorStatus::APPROVED);
                })->orWhereHas('invites', function ($inviteQuery) use ($user) {
                    $inviteQuery->where('email', strtolower($user->email));
                });
            })
            ->orderByDesc('updated_at')
            ->get();

        $shared->each(function (Diagram $diagram) {
            $visitor = $diagram->visitors->first();
            $invite = $diagram->invites->first();
            $diagram->setAttribute('effective_access', $invite?->access?->value ?? $visitor?->access?->value ?? $diagram->share_access?->value);
        });

        $sharedIds = $shared->pluck('id')->all();

        $public = Diagram::query()
            ->with('user:id,email')
            ->select($this->summaryColumns())
            ->where('library', true)
            ->whereNotNull('share_access')
            ->when($sharedIds !== [], fn ($query) => $query->whereNotIn('id', $sharedIds))
            ->whereDoesntHave('visitors', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', VisitorStatus::REVOKED);
            })
            ->orderByDesc('updated_at')
            ->get();

        $public->each(fn (Diagram $diagram) => $diagram->setAttribute(
            'effective_access',
            $diagram->share_access?->value === 'write' ? 'write' : 'read'
        ));

        return compact('owned', 'shared', 'public');
    }

    /** @deprecated Not used anywhere */
    public function find(int $id): Diagram
    {
        return Diagram::find($id);
    }

    public function create(CreateDiagramDTO $dto): Diagram
    {
        return Diagram::create([
            'name' => $dto->name,
            'db_type' => $dto->dbType,
            'schema' => null,
            'user_id' => $dto->userId,
            'share_access' => $dto->shareAccess,
            'library' => $dto->library,
        ]);
    }

    public function update(Diagram $diagram, UpdateDiagramDTO $dto): bool
    {
        $data = $dto->toArray();
        if (isset($data['schema']) && is_array($data['schema'])) {
            $data['schema'] = DiagramSchema::withoutRuntimeState($data['schema']);
        }
        $importIsActive = in_array(
            $diagram->import_status,
            [ImportStatus::PENDING, ImportStatus::PROCESSING],
            true
        );
        if (! $importIsActive
            && (array_key_exists('schema', $data) || array_key_exists('value_types', $data))) {
            $data['import_status'] = null;
            $data['import_error'] = null;
            $data['import_warnings'] = null;
        }

        return $diagram->update($data);
    }

    public function delete(Diagram $diagram): bool
    {
        return $diagram->delete();
    }

    /** @return list<string> */
    private function summaryColumns(): array
    {
        return [
            'id',
            'name',
            'db_type',
            'user_id',
            'share_token',
            'share_access',
            'require_approval',
            'library',
            'schema',
            'updated_at',
        ];
    }
}
