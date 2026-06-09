<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Models\Diagram;
use App\Models\User;
use App\Support\DiagramSchema;
use Illuminate\Database\Eloquent\Collection;

class DiagramRepository implements DiagramRepositoryInterface
{
    /** @return Collection<int, Diagram> */
    public function all(User $user): Collection
    {
        return $user->diagrams()->get();
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

        return $diagram->update($data);
    }

    public function delete(Diagram $diagram): bool
    {
        return $diagram->delete();
    }
}
