<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Diagram;
use App\Models\User;
use App\Services\DiagramSharingService;

class DiagramPolicy
{
    public function view(User $user, Diagram $diagram): bool
    {
        return $this->ownsDiagram($user, $diagram);
    }

    public function update(User $user, Diagram $diagram): bool
    {
        return $this->ownsDiagram($user, $diagram);
    }

    public function delete(User $user, Diagram $diagram): bool
    {
        return $this->ownsDiagram($user, $diagram);
    }

    public function import(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canWrite($diagram, $user);
    }

    public function export(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canRead($diagram, $user);
    }

    public function viewChangelog(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canRead($diagram, $user);
    }

    public function addChangelog(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canWrite($diagram, $user);
    }

    /**
     * View Foundry config/status and query Foundry resources, and connect the
     * user's own Foundry account. Requires read access to the diagram.
     */
    public function viewFoundry(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canRead($diagram, $user);
    }

    /**
     * Change the diagram's Foundry host/defaults. Owner only.
     */
    public function manageFoundry(User $user, Diagram $diagram): bool
    {
        return $this->ownsDiagram($user, $diagram);
    }

    /**
     * View diagram agent sessions/messages and the model list. Agent history is
     * shared across collaborators, so read access is sufficient.
     */
    public function viewAgent(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canRead($diagram, $user);
    }

    /**
     * Start a session, send a prompt, or archive a session. Sending the full
     * diagram to the model and producing edit patches requires write access.
     */
    public function useAgent(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canWrite($diagram, $user);
    }

    private function ownsDiagram(User $user, Diagram $diagram): bool
    {
        return $user->id === $diagram->user_id;
    }
}
