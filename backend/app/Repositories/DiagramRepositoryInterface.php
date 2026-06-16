<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface DiagramRepositoryInterface
{
    /** @return Collection<int, Diagram> */
    public function all(User $user): Collection;

    /** @return array{owned: Collection<int, Diagram>, shared: Collection<int, Diagram>, public: Collection<int, Diagram>} */
    public function dashboard(User $user): array;

    /** @deprecated Not used anywhere */
    public function find(int $id): Diagram;

    public function create(CreateDiagramDTO $dto): Diagram;

    public function update(Diagram $diagram, UpdateDiagramDTO $dto): bool;

    public function delete(Diagram $diagram): bool;
}
