<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Models\Diagram;
use App\Models\User;
use App\Services\DiagramCrudService;
use Tests\TestCase;

class DiagramCrudServiceTest extends TestCase
{

    private DiagramCrudService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DiagramCrudService::class);
    }

    public function test_get_user_diagrams(): void
    {
        $user = User::factory()->create();
        $diagram = Diagram::factory()->create(['user_id' => $user->id]);
        $this->assertEquals($diagram->id, $this->service->getUserDiagrams($user)[0]->id);
    }

    public function test_create_diagram(): void
    {
        $dto = new CreateDiagramDTO(name: 'test', userId: User::factory()->create()->id);
        $this->assertDatabaseHas(Diagram::class, $this->service->createDiagram($dto)->only(['name', 'user_id']));
    }

    public function test_update_diagram(): void
    {
        $this->assertTrue($this->service->updateDiagram(Diagram::factory()->create(), new UpdateDiagramDTO(name: 'Updated')));
    }

    public function test_delete_diagram(): void
    {
        $diagram = Diagram::factory()->create();
        $this->assertTrue($this->service->deleteDiagram($diagram));
        $this->assertDatabaseMissing(Diagram::class, ['id' => $diagram->id]);
    }

}
