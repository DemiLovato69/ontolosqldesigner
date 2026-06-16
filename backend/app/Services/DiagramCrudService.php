<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Models\Diagram;
use App\Models\User;
use App\Repositories\DiagramRepositoryInterface;
use App\Support\DiagramSchema;
use Illuminate\Database\Eloquent\Collection;

class DiagramCrudService
{
    public function __construct(protected DiagramRepositoryInterface $diagramRepository) {}

    /** @return Collection<int, Diagram> */
    public function getUserDiagrams(User $user): Collection
    {
        return $this->diagramRepository->all($user);
    }

    /** @return array{owned: Collection<int, Diagram>, shared: Collection<int, Diagram>, public: Collection<int, Diagram>} */
    public function getDashboardDiagrams(User $user): array
    {
        return $this->diagramRepository->dashboard($user);
    }

    public function createDiagram(CreateDiagramDTO $dto): Diagram
    {
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

    public function duplicateForUser(Diagram $source, User $user): Diagram
    {
        return Diagram::create([
            'name' => $this->copyName($source->name, $user),
            'db_type' => $source->db_type,
            'schema' => DiagramSchema::withoutRuntimeState($source->schema),
            'value_types' => $source->value_types ?? [],
            'script' => $source->script,
            'user_id' => $user->id,
            'share_access' => null,
            'require_approval' => false,
            'library' => false,
            'featured' => false,
            'featured_url' => null,
            'import_status' => null,
            'import_error' => null,
            'import_warnings' => null,
            'export_status' => null,
            'export_error' => null,
            'export_json' => null,
        ]);
    }

    /** @return array{name: string, db_type: string, schema: mixed} */
    public function getEmbedData(Diagram $diagram): array
    {
        return [
            'name' => $diagram->name,
            'db_type' => $diagram->db_type->value,
            'schema' => DiagramSchema::withoutRuntimeState($diagram->schema),
        ];
    }

    private function copyName(string $name, User $user): string
    {
        $base = "Copy of {$name}";
        if (! $user->diagrams()->where('name', $base)->exists()) {
            return $base;
        }

        for ($i = 2; ; $i++) {
            $candidate = "{$base} ({$i})";
            if (! $user->diagrams()->where('name', $candidate)->exists()) {
                return $candidate;
            }
        }
    }
}
