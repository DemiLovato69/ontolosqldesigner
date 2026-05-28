<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\DiagramRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DiagramCrudService
{
    public function __construct(protected DiagramRepositoryInterface $diagramRepository) {}

    public function getUserDiagrams(User $user): Collection
    {
        return $this->diagramRepository->all($user);
    }

    /**
     * @throws ValidationException
     */
    public function createDiagram(CreateDiagramDTO $dto): Diagram
    {
        $exists = Diagram::where('user_id', $dto->userId)
            ->where('name', $dto->name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A diagram with this name already exists.'],
            ]);
        }

        return $this->diagramRepository->create($dto);
    }

    public function updateDiagram(Diagram $diagram, UpdateDiagramDTO $dto): bool
    {
        return $this->diagramRepository->update($diagram, $dto);
    }

    public function deleteDiagram(Diagram $diagram): bool
    {
        return $this->diagramRepository->delete($diagram);
    }

    /** @return array{name: string, db_type: string, schema: mixed} */
    public function getEmbedData(Diagram $diagram): array
    {
        return [
            'name'    => $diagram->name,
            'db_type' => $diagram->db_type->value,
            'schema'  => $diagram->schema,
        ];
    }
}
