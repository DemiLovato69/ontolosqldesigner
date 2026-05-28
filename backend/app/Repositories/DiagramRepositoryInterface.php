<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface DiagramRepositoryInterface
{
    public function all(User $user): Collection;

    public function find(int $id): Diagram;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Diagram;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Diagram $diagram, array $data): bool;

    public function delete(Diagram $diagram): bool;
}
