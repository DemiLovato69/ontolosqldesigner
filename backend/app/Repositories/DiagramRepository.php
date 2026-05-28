<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DiagramRepository implements DiagramRepositoryInterface
{
    public function all(User $user): Collection
    {
        return $user->diagrams()->get();
    }

    /** @deprecated Not used anywhere */
    public function find(int $id): Diagram
    {
        return Diagram::find($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Diagram
    {
        return Diagram::create([
            'name'         => $data['name'],
            'db_type'      => $data['db_type'] ?? 'mysql',
            'schema'       => null,
            'user_id'      => $data['user_id'],
            'share_access' => $data['share_access'] ?? null,
            'library'      => $data['library'] ?? false,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Diagram $diagram, array $data): bool
    {
        return $diagram->update($data);
    }

    public function delete(Diagram $diagram): bool
    {
        return $diagram->delete();
    }
}
