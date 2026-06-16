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
        return $this->ownsDiagram($user, $diagram);
    }

    public function export(User $user, Diagram $diagram): bool
    {
        return $this->ownsDiagram($user, $diagram);
    }

    public function viewChangelog(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canRead($diagram, $user);
    }

    public function addChangelog(User $user, Diagram $diagram): bool
    {
        return app(DiagramSharingService::class)->canWrite($diagram, $user);
    }

    private function ownsDiagram(User $user, Diagram $diagram): bool
    {
        return $user->id === $diagram->user_id;
    }
}
