<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ImportStatus;
use App\Models\Diagram;
use App\Models\DiagramVisitor;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiagramApiTest extends TestCase
{
    private const DESKTOP_ABILITIES = [
        'desktop',
        'diagrams:read',
        'diagrams:write',
        'diagrams:delete',
        'imports:write',
        'exports:read',
        'sharing:write',
        'changelog:read',
        'changelog:write',
        'presence:read',
        'presence:write',
        'tokens:manage',
    ];

    private User $user;

    private Diagram $diagram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->diagram = Diagram::factory()->create([
            'user_id' => $this->user->id,
            'db_type' => 'ontology',
            'schema' => [],
        ]);
    }

    public function test_bearer_token_dashboard_returns_owned_shared_and_public_diagrams(): void
    {
        $coworker = User::factory()->create(['email_verified_at' => now()]);
        $shared = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'name' => 'Shared diagram',
            'share_access' => 'per_user',
        ]);
        DiagramVisitor::factory()->create([
            'diagram_id' => $shared->id,
            'user_id' => $this->user->id,
            'status' => 'approved',
            'access' => 'write',
        ]);
        $public = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'name' => 'Public diagram',
            'share_access' => 'read',
            'library' => true,
        ]);

        $this->withDesktopToken()
            ->getJson('/api/v1/diagrams/dashboard')
            ->assertOk()
            ->assertJsonPath('owned.0.id', $this->diagram->id)
            ->assertJsonPath('shared.0.id', $shared->id)
            ->assertJsonPath('shared.0.effective_access', 'write')
            ->assertJsonPath('public.0.id', $public->id)
            ->assertJsonPath('public.0.effective_access', 'read');
    }

    public function test_bearer_token_show_returns_full_diagram_metadata_without_runtime_schema_state(): void
    {
        $this->diagram->update([
            'schema' => [[
                'id' => 'table-1',
                'type' => 'table',
                'label' => 'Customer',
                'position' => ['x' => 10, 'y' => 20],
                'computedPosition' => ['x' => 10, 'y' => 20, 'z' => 1],
                'selected' => true,
                'data' => ['description' => 'Customer object', 'editing' => true],
            ]],
            'value_types' => [['id' => 'email-type', 'apiName' => 'emailAddress', 'baseType' => ['type' => 'string']]],
            'interfaces' => [['id' => 'iface-1', 'apiName' => 'CustomerInterface']],
            'interface_link_constraints' => [['id' => 'constraint-1', 'apiName' => 'customerToAccount']],
            'custom_actions' => [['id' => 'action-1', 'apiName' => 'approveCustomer']],
            'shared_property_types' => [['id' => 'shared-1', 'apiName' => 'externalId']],
        ]);

        $this->withDesktopToken()
            ->getJson("/api/v1/diagrams/{$this->diagram->id}")
            ->assertOk()
            ->assertJsonPath('data.db_type', 'ontology')
            ->assertJsonPath('data.schema.0.position.x', 10)
            ->assertJsonMissingPath('data.schema.0.computedPosition')
            ->assertJsonMissingPath('data.schema.0.selected')
            ->assertJsonMissingPath('data.schema.0.data.editing')
            ->assertJsonPath('data.value_types.0.apiName', 'emailAddress')
            ->assertJsonPath('data.interfaces.0.apiName', 'CustomerInterface')
            ->assertJsonPath('data.interface_link_constraints.0.apiName', 'customerToAccount')
            ->assertJsonPath('data.custom_actions.0.apiName', 'approveCustomer')
            ->assertJsonPath('data.shared_property_types.0.apiName', 'externalId');
    }

    public function test_bearer_token_can_create_update_and_delete_diagrams_via_v1_routes(): void
    {
        $createdId = $this->withDesktopToken()
            ->postJson('/api/v1/diagrams', ['name' => 'Desktop diagram', 'db_type' => 'ontology'])
            ->assertCreated()
            ->assertJsonFragment(['status' => true])
            ->json('diagram.id');

        $this->withDesktopToken()
            ->patchJson("/api/v1/diagrams/{$createdId}", [
                'name' => 'Renamed desktop diagram',
                'interfaces' => [['id' => 'iface-1', 'apiName' => 'RenamedInterface']],
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => true]);

        $this->assertDatabaseHas('diagrams', [
            'id' => $createdId,
            'name' => 'Renamed desktop diagram',
            'db_type' => 'ontology',
        ]);

        $this->withDesktopToken()
            ->deleteJson("/api/v1/diagrams/{$createdId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('diagrams', ['id' => $createdId]);
    }

    public function test_bearer_token_shared_routes_show_save_and_duplicate_by_share_token(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $shared = Diagram::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Shared source',
            'share_access' => 'write',
            'schema' => [['id' => 'original', 'type' => 'table', 'label' => 'Original']],
        ]);

        $this->withDesktopToken()
            ->getJson("/api/v1/shared/diagrams/{$shared->share_token}")
            ->assertOk()
            ->assertJsonPath('data.id', $shared->id)
            ->assertJsonPath('data.is_owner', false);

        $this->withDesktopToken()
            ->patchJson("/api/v1/shared/diagrams/{$shared->share_token}", [
                'schema' => [['id' => 'updated', 'type' => 'table', 'label' => 'Updated']],
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => true]);

        $this->assertSame('updated', $shared->refresh()->schema[0]['id']);

        $copyId = $this->withDesktopToken()
            ->postJson("/api/v1/shared/diagrams/{$shared->share_token}/duplicate")
            ->assertCreated()
            ->assertJsonFragment(['status' => true])
            ->json('diagram.id');

        $copy = Diagram::findOrFail($copyId);
        $this->assertSame($this->user->id, $copy->user_id);
        $this->assertSame('Copy of Shared source', $copy->name);
        $this->assertNull($copy->share_access);
    }

    public function test_bearer_token_v1_import_export_and_changelog_routes_work(): void
    {
        Queue::fake();
        Event::fake();
        $token = $this->desktopToken();

        $this->call(
                'POST',
                "/api/v1/diagrams/{$this->diagram->id}/imports/backup-json",
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'text/plain',
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                ],
                '{"format":"ontolosql-designer"}'
            )
            ->assertAccepted()
            ->assertJsonPath('status', 'pending');

        $queued = json_decode($this->diagram->refresh()->script, true);
        $this->assertSame('backup-json', $queued['format']);
        $this->assertSame(ImportStatus::PENDING, $this->diagram->import_status);

        $this->withDesktopToken()
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/imports/status")
            ->assertOk()
            ->assertJsonPath('status', ImportStatus::PENDING->value);

        $this->withDesktopToken()
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/exports")
            ->assertAccepted()
            ->assertJsonPath('status', 'pending');

        $this->withDesktopToken()
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/exports/backup-json")
            ->assertOk()
            ->assertJsonPath('format', 'ontolosql-designer')
            ->assertJsonPath('version', 2);

        $this->withDesktopToken()
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/changelog", ['action' => 'desktop_save'])
            ->assertCreated()
            ->assertJsonFragment(['status' => true]);

        $response = $this->withDesktopToken()
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/changelog")
            ->assertOk();

        $this->assertContains('desktop_save', collect($response->json('data'))->pluck('action')->all());
    }

    public function test_v1_routes_require_matching_token_abilities(): void
    {
        $this->withDesktopToken(['desktop', 'diagrams:read'])
            ->getJson('/api/v1/diagrams/dashboard')
            ->assertOk();

        $this->withDesktopToken(['desktop', 'diagrams:read'])
            ->patchJson("/api/v1/diagrams/{$this->diagram->id}", ['name' => 'Blocked'])
            ->assertForbidden();

        $this->withDesktopToken(['desktop', 'diagrams:read'])
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/exports")
            ->assertForbidden();

        $this->withDesktopToken(['diagrams:read', 'diagrams:write'])
            ->getJson('/api/v1/diagrams/dashboard')
            ->assertForbidden();
    }

    /** @param list<string> $abilities */
    private function withDesktopToken(array $abilities = self::DESKTOP_ABILITIES): static
    {
        $token = $this->desktopToken($abilities);

        return $this->withHeader('Accept', 'application/json')->withToken($token);
    }

    /** @param list<string> $abilities */
    private function desktopToken(array $abilities = self::DESKTOP_ABILITIES): string
    {
        $token = $this->user->createToken('Dioxus Desktop', $abilities, now()->addHour());
        Auth::forgetGuards();

        return $token->plainTextToken;
    }
}
