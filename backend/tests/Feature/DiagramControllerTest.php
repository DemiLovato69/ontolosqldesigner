<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiagramControllerTest extends TestCase
{

    private User $user;

    private Diagram $diagram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->diagram = Diagram::factory()->create(['user_id' => $this->user->id, 'schema' => []]);
    }

    private function auth(): static
    {
        return $this->actingAs($this->user, 'sanctum');
    }

    public function test_index_returns_diagrams(): void
    {
        $this->diagram->update([
            'schema' => [[
                'id' => 'large-table',
                'type' => 'table',
                'label' => str_repeat('large schema data', 1000),
            ]],
        ]);

        $this->auth()
            ->getJson('/api/diagrams')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->diagram->id)
            ->assertJsonPath('data.0.schema', [])
            ->assertJsonMissingPath('data.0.schema.0');
    }

    public function test_store_creates_diagram(): void
    {
        $this->auth()
            ->postJson('/api/diagrams', ['name' => 'New '.uniqid()])
            ->assertStatus(201)
            ->assertJsonFragment(['status' => true])
            ->assertJsonStructure(['diagram' => ['id', 'share_token']]);
    }

    public function test_store_creates_ontology_diagram(): void
    {
        $name = 'Ontology '.uniqid();

        $this->auth()
            ->postJson('/api/diagrams', ['name' => $name, 'db_type' => 'ontology'])
            ->assertStatus(201)
            ->assertJsonFragment(['status' => true]);

        $this->assertDatabaseHas('diagrams', [
            'name' => $name,
            'db_type' => 'ontology',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_show_returns_diagram(): void
    {
        $this->auth()
            ->getJson("/api/diagrams/{$this->diagram->id}")
            ->assertStatus(200);
    }

    public function test_update_saves_diagram(): void
    {
        $this->auth()
            ->putJson("/api/diagrams/{$this->diagram->id}", ['name' => 'Updated '.uniqid()])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);
    }

    public function test_schema_runtime_state_is_not_stored_or_returned(): void
    {
        $schema = [[
            'id' => 'table-1',
            'type' => 'table',
            'label' => 'users',
            'position' => ['x' => 10, 'y' => 20],
            'computedPosition' => ['x' => 10, 'y' => 20, 'z' => 1],
            'dimensions' => ['width' => 350, 'height' => 40],
            'handleBounds' => ['source' => [], 'target' => []],
            'selected' => true,
            'data' => ['description' => 'Users', 'editing' => true],
        ]];

        $this->auth()
            ->putJson("/api/diagrams/{$this->diagram->id}", ['schema' => $schema])
            ->assertStatus(200);

        $stored = $this->diagram->refresh()->schema[0];
        $this->assertArrayNotHasKey('computedPosition', $stored);
        $this->assertArrayNotHasKey('dimensions', $stored);
        $this->assertArrayNotHasKey('handleBounds', $stored);
        $this->assertArrayNotHasKey('selected', $stored);
        $this->assertArrayNotHasKey('editing', $stored['data']);

        $this->auth()
            ->getJson("/api/diagrams/{$this->diagram->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.schema.0.position.x', 10)
            ->assertJsonMissingPath('data.schema.0.computedPosition');
    }

    public function test_destroy_deletes_diagram(): void
    {
        $diagram = Diagram::factory()->create(['user_id' => $this->user->id]);

        $this->auth()
            ->deleteJson("/api/diagrams/{$diagram->id}")
            ->assertStatus(204);
    }

    public function test_share_returns_access(): void
    {
        $this->auth()
            ->postJson("/api/diagrams/{$this->diagram->id}/share")
            ->assertStatus(200)
            ->assertJsonStructure(['share_access']);
    }

    public function test_unshare_succeeds(): void
    {
        $this->auth()
            ->deleteJson("/api/diagrams/{$this->diagram->id}/share")
            ->assertStatus(204);
    }

    public function test_update_share_access_succeeds(): void
    {
        $this->auth()
            ->patchJson("/api/diagrams/{$this->diagram->id}/share", ['access' => 'read'])
            ->assertStatus(200)
            ->assertJsonStructure(['share_access']);
    }

    public function test_get_visitors_returns_list(): void
    {
        $this->auth()
            ->getJson("/api/diagrams/{$this->diagram->id}/visitors")
            ->assertStatus(200);
    }

    public function test_import_returns_pending(): void
    {
        Queue::fake();
        Event::fake();

        $this->auth()
            ->postJson("/api/diagrams/sql/import/{$this->diagram->id}", ['script' => 'CREATE TABLE t (id INT);'])
            ->assertStatus(202)
            ->assertJsonFragment(['status' => 'pending']);
    }

    public function test_import_accepts_raw_script_body(): void
    {
        Queue::fake();
        Event::fake();

        $this->auth()
            ->call(
                'POST',
                "/api/diagrams/sql/import/{$this->diagram->id}",
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'text/plain'],
                'CREATE TABLE users (id INT PRIMARY KEY);'
            )
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $queued = json_decode($this->diagram->refresh()->script, true);
        $this->assertSame('sql', $queued['format']);
        $this->assertSame('CREATE TABLE users (id INT PRIMARY KEY);', $queued['content']);
    }

    public function test_import_format_route_preserves_selected_format(): void
    {
        Queue::fake();
        Event::fake();

        $this->auth()
            ->call(
                'POST',
                "/api/diagrams/import/backup-json/{$this->diagram->id}",
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'text/plain'],
                '{"format":"ontolosql-designer"}'
            )
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $queued = json_decode($this->diagram->refresh()->script, true);
        $this->assertSame('backup-json', $queued['format']);
        $this->assertSame('{"format":"ontolosql-designer"}', $queued['content']);
    }

    public function test_import_rejects_empty_raw_script_body(): void
    {
        $this->auth()
            ->call(
                'POST',
                "/api/diagrams/sql/import/{$this->diagram->id}",
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'text/plain'],
                ''
            )
            ->assertStatus(422);
    }

    public function test_import_status_returns_status(): void
    {
        $this->auth()
            ->getJson("/api/diagrams/sql/import-status/{$this->diagram->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['status']);
    }

    public function test_export_returns_pending(): void
    {
        Queue::fake();

        $this->auth()
            ->postJson("/api/diagrams/sql/export/{$this->diagram->id}")
            ->assertStatus(202)
            ->assertJsonFragment(['status' => 'pending']);
    }

    public function test_export_status_returns_status(): void
    {
        $this->auth()
            ->getJson("/api/diagrams/sql/export-status/{$this->diagram->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['status']);
    }

    public function test_export_json_returns_ok(): void
    {
        $this->auth()
            ->getJson("/api/diagrams/json/export/{$this->diagram->id}")
            ->assertStatus(200);
    }

    public function test_export_json_includes_ontology_value_types(): void
    {
        $this->diagram->update([
            'db_type' => 'ontology',
            'schema' => [
                ['id' => 't1', 'type' => 'table', 'label' => 'users'],
                ['id' => 'r1', 'type' => 'row', 'label' => 'email', 'parentNode' => 't1', 'data' => [
                    'sqlType' => 'STRING',
                    'valueTypeId' => 'email-type',
                ]],
            ],
            'value_types' => [[
                'id' => 'email-type',
                'apiName' => 'emailAddress',
                'displayName' => 'Email Address',
                'version' => '1.0.0',
                'baseType' => ['type' => 'string'],
                'constraints' => [],
            ]],
        ]);

        $this->auth()
            ->getJson("/api/diagrams/json/export/{$this->diagram->id}")
            ->assertOk()
            ->assertJsonPath('format', 'ontolosql-designer')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('diagram.dbType', 'ontology')
            ->assertJsonPath('diagram.valueTypes.0.apiName', 'emailAddress')
            ->assertJsonPath('diagram.schema.1.data.valueTypeId', 'email-type');
    }

    public function test_export_ontology_includes_value_types(): void
    {
        $this->diagram->update([
            'db_type' => 'ontology',
            'schema' => [
                ['id' => 't1', 'type' => 'table', 'label' => 'users'],
                ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['keyMod' => 'PRIMARY KEY', 'sqlType' => 'STRING']],
                ['id' => 'r2', 'type' => 'row', 'label' => 'email', 'parentNode' => 't1', 'data' => [
                    'sqlType' => 'STRING',
                    'valueTypeId' => 'email-type',
                ]],
            ],
            'value_types' => [[
                'id' => 'email-type',
                'apiName' => 'emailAddress',
                'displayName' => 'Email Address',
                'version' => '1.0.0',
                'baseType' => ['type' => 'string'],
                'constraints' => [],
            ]],
        ]);

        $response = $this->auth()->get("/api/diagrams/ontology/export/{$this->diagram->id}");

        $response->assertOk();
        $this->assertStringContainsString('export const emailAddress = defineValueType({', $response->getContent());
        $this->assertStringContainsString('valueType: emailAddress', $response->getContent());
    }

    public function test_export_migration_returns_zip(): void
    {
        // ZipArchive::close() deletes the temp file when no entries are added (empty schema).
        // Provide a schema with one table so the archive is non-empty and survives close().
        $this->diagram->update(['schema' => [
            ['id' => 't1', 'type' => 'table', 'label' => 'users', 'data' => ['uniqueTogether' => [], 'fulltextIndexes' => []]],
            ['id' => 'r1', 'type' => 'row', 'label' => 'id', 'parentNode' => 't1', 'data' => ['sqlType' => 'INT', 'nullable' => false, 'unsigned' => false, 'keyMod' => 'PRIMARY KEY', 'defaultValue' => null, 'comment' => null]],
        ]]);

        $this->auth()
            ->get("/api/diagrams/migration/export/{$this->diagram->id}")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_show_embed_returns_data(): void
    {
        $this->diagram->update(['share_access' => 'read']);

        $this->getJson("/api/diagrams/embed/{$this->diagram->share_token}")
            ->assertStatus(200);
    }

    public function test_show_by_token_returns_diagram(): void
    {
        $this->auth()
            ->getJson("/api/diagrams/shared/{$this->diagram->share_token}")
            ->assertStatus(200);
    }

    public function test_show_returns_403_for_non_owner(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/diagrams/{$this->diagram->id}")
            ->assertStatus(403);
    }

    public function test_update_returns_403_for_non_owner(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/diagrams/{$this->diagram->id}", ['name' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_destroy_returns_403_for_non_owner(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/diagrams/{$this->diagram->id}")
            ->assertStatus(403);
    }
}
