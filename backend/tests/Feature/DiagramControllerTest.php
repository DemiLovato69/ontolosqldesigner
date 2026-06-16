<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Models\Diagram;
use App\Models\DiagramImport;
use App\Models\DiagramVisitor;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_index_returns_diagram_schema_preview(): void
    {
        $this->diagram->update([
            'schema' => [
                [
                    'id' => 'users',
                    'type' => 'table',
                    'label' => 'users',
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'id' => 'users-id',
                    'type' => 'row',
                    'label' => 'id',
                    'parentNode' => 'users',
                    'position' => ['x' => 0, 'y' => 40],
                ],
            ],
        ]);

        $this->auth()
            ->getJson('/api/diagrams')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->diagram->id)
            ->assertJsonPath('data.0.schema.0.id', 'users')
            ->assertJsonPath('data.0.schema.0.type', 'table')
            ->assertJsonPath('data.0.schema.1.id', 'users-id')
            ->assertJsonPath('data.0.schema.1.type', 'row');
    }

    public function test_index_schema_preview_is_limited(): void
    {
        $schema = [];

        for ($table = 1; $table <= 20; $table++) {
            $tableId = "table-{$table}";

            $schema[] = [
                'id' => $tableId,
                'type' => 'table',
                'label' => $tableId,
                'position' => ['x' => $table * 420, 'y' => 0],
            ];

            for ($row = 1; $row <= 10; $row++) {
                $schema[] = [
                    'id' => "{$tableId}-row-{$row}",
                    'type' => 'row',
                    'label' => "row_{$row}",
                    'parentNode' => $tableId,
                    'position' => ['x' => 0, 'y' => $row * 40],
                ];
            }
        }

        $this->diagram->update(['schema' => $schema]);

        $response = $this->auth()
            ->getJson('/api/diagrams')
            ->assertStatus(200);

        $previewSchema = $response->json('data.0.schema');

        $this->assertNotEmpty($previewSchema);
        $this->assertLessThan(count($schema), count($previewSchema));
    }

    public function test_index_schema_preview_only_includes_rows_for_previewed_tables(): void
    {
        $this->diagram->update([
            'schema' => [
                ['id' => 'included-table', 'type' => 'table', 'label' => 'Included', 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'included-row', 'type' => 'row', 'label' => 'id', 'parentNode' => 'included-table', 'position' => ['x' => 0, 'y' => 40]],
                ['id' => 'orphan-row', 'type' => 'row', 'label' => 'orphan', 'parentNode' => 'missing-table', 'position' => ['x' => 0, 'y' => 40]],
            ],
        ]);

        $response = $this->auth()
            ->getJson('/api/diagrams')
            ->assertStatus(200);

        $previewIds = collect($response->json('data.0.schema'))->pluck('id')->all();

        $this->assertContains('included-table', $previewIds);
        $this->assertContains('included-row', $previewIds);
        $this->assertNotContains('orphan-row', $previewIds);
    }

    public function test_index_schema_preview_only_includes_edges_for_previewed_rows(): void
    {
        $this->diagram->update([
            'schema' => [
                ['id' => 'users', 'type' => 'table', 'label' => 'users', 'position' => ['x' => 0, 'y' => 0]],
                ['id' => 'orders', 'type' => 'table', 'label' => 'orders', 'position' => ['x' => 420, 'y' => 0]],
                ['id' => 'users-id', 'type' => 'row', 'label' => 'id', 'parentNode' => 'users', 'position' => ['x' => 0, 'y' => 40]],
                ['id' => 'orders-user-id', 'type' => 'row', 'label' => 'user_id', 'parentNode' => 'orders', 'position' => ['x' => 0, 'y' => 40]],
                ['id' => 'valid-edge', 'type' => 'chickenFoot', 'source' => 'orders-user-id', 'target' => 'users-id'],
                ['id' => 'missing-source-edge', 'type' => 'chickenFoot', 'source' => 'missing-row', 'target' => 'users-id'],
            ],
        ]);

        $response = $this->auth()
            ->getJson('/api/diagrams')
            ->assertStatus(200);

        $previewIds = collect($response->json('data.0.schema'))->pluck('id')->all();

        $this->assertContains('valid-edge', $previewIds);
        $this->assertNotContains('missing-source-edge', $previewIds);
    }

    public function test_dashboard_returns_owned_shared_and_public_diagrams(): void
    {
        $coworker = User::factory()->create(['email_verified_at' => now()]);
        $sharedDiagram = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'name' => 'Shared diagram',
            'share_access' => 'per_user',
        ]);
        DiagramVisitor::factory()->create([
            'diagram_id' => $sharedDiagram->id,
            'user_id' => $this->user->id,
            'status' => 'approved',
            'access' => 'write',
        ]);

        $publicDiagram = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'name' => 'Public diagram',
            'share_access' => 'read',
            'library' => true,
        ]);

        $this->auth()
            ->getJson('/api/diagrams/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('owned.0.id', $this->diagram->id)
            ->assertJsonPath('shared.0.id', $sharedDiagram->id)
            ->assertJsonPath('shared.0.effective_access', 'write')
            ->assertJsonPath('public.0.id', $publicDiagram->id)
            ->assertJsonPath('public.0.effective_access', 'read');
    }

    public function test_dashboard_returns_email_invited_diagrams_as_shared(): void
    {
        $coworker = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'share_access' => 'read',
        ]);
        $diagram->invites()->create(['email' => strtolower($this->user->email), 'access' => 'write']);

        $this->auth()
            ->getJson('/api/diagrams/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('shared.0.id', $diagram->id)
            ->assertJsonPath('shared.0.effective_access', 'write');
    }

    public function test_dashboard_returns_owned_public_diagrams_as_public(): void
    {
        $diagram = Diagram::factory()->create([
            'user_id' => $this->user->id,
            'share_access' => 'read',
            'library' => true,
        ]);

        $response = $this->auth()
            ->getJson('/api/diagrams/dashboard')
            ->assertStatus(200);

        $publicIds = collect($response->json('public'))->pluck('id')->all();

        $this->assertContains($diagram->id, $publicIds);
    }

    public function test_dashboard_excludes_pending_revoked_and_duplicate_public_diagrams(): void
    {
        $coworker = User::factory()->create(['email_verified_at' => now()]);
        $pendingDiagram = Diagram::factory()->create(['user_id' => $coworker->id, 'share_access' => 'per_user']);
        DiagramVisitor::factory()->create([
            'diagram_id' => $pendingDiagram->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $revokedPublicDiagram = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'share_access' => 'read',
            'library' => true,
        ]);
        DiagramVisitor::factory()->create([
            'diagram_id' => $revokedPublicDiagram->id,
            'user_id' => $this->user->id,
            'status' => 'revoked',
        ]);

        $sharedPublicDiagram = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'share_access' => 'read',
            'library' => true,
        ]);
        DiagramVisitor::factory()->create([
            'diagram_id' => $sharedPublicDiagram->id,
            'user_id' => $this->user->id,
            'status' => 'approved',
            'access' => 'read',
        ]);

        $response = $this->auth()
            ->getJson('/api/diagrams/dashboard')
            ->assertStatus(200);

        $sharedIds = collect($response->json('shared'))->pluck('id')->all();
        $publicIds = collect($response->json('public'))->pluck('id')->all();

        $this->assertContains($sharedPublicDiagram->id, $sharedIds);
        $this->assertNotContains($pendingDiagram->id, $sharedIds);
        $this->assertNotContains($revokedPublicDiagram->id, $publicIds);
        $this->assertNotContains($sharedPublicDiagram->id, $publicIds);
    }

    public function test_duplicate_shared_diagram_creates_private_owned_copy(): void
    {
        $coworker = User::factory()->create(['email_verified_at' => now()]);
        $source = Diagram::factory()->create([
            'user_id' => $coworker->id,
            'name' => 'Company model',
            'db_type' => 'ontology',
            'share_access' => 'read',
            'library' => true,
            'schema' => [['id' => 'table-1', 'type' => 'table', 'label' => 'users']],
            'value_types' => [['id' => 'email-type', 'apiName' => 'Email', 'displayName' => 'Email', 'version' => '1.0.0', 'baseType' => ['type' => 'string']]],
        ]);

        $response = $this->auth()
            ->postJson("/api/diagrams/shared/{$source->share_token}/duplicate")
            ->assertStatus(201)
            ->assertJsonFragment(['status' => true]);

        $copy = Diagram::findOrFail($response->json('diagram.id'));

        $this->assertSame($this->user->id, $copy->user_id);
        $this->assertSame('Copy of Company model', $copy->name);
        $this->assertSame($source->schema, $copy->schema);
        $this->assertSame($source->value_types, $copy->value_types);
        $this->assertNull($copy->share_access);
        $this->assertFalse((bool) $copy->library);
        $this->assertNotSame($source->share_token, $copy->share_token);
    }

    public function test_duplicate_requires_shared_access(): void
    {
        $coworker = User::factory()->create(['email_verified_at' => now()]);
        $source = Diagram::factory()->create(['user_id' => $coworker->id, 'share_access' => null]);

        $this->auth()
            ->postJson("/api/diagrams/shared/{$source->share_token}/duplicate")
            ->assertStatus(403);
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

    public function test_schema_autosave_does_not_cancel_active_import(): void
    {
        $this->diagram->update(['import_status' => ImportStatus::PENDING]);

        $this->auth()
            ->putJson("/api/diagrams/{$this->diagram->id}", [
                'schema' => [['id' => 'current', 'type' => 'table', 'label' => 'Current']],
                'value_types' => [],
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        $this->assertSame(ImportStatus::PENDING, $this->diagram->refresh()->import_status);
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

    public function test_update_saves_large_nested_schema_without_exhausting_validation_memory(): void
    {
        $schema = [];
        $description = str_repeat('ontology property metadata ', 40);

        for ($index = 0; $index < 6000; $index++) {
            $schema[] = [
                'id' => "node-{$index}",
                'type' => 'table',
                'position' => ['x' => $index % 100 * 420, 'y' => intdiv($index, 100) * 300],
                'data' => [
                    'name' => "ObjectType{$index}",
                    'description' => $description,
                    'properties' => [
                        ['id' => "property-{$index}", 'name' => 'identifier', 'dataType' => 'string'],
                    ],
                ],
            ];
        }

        $this->auth()
            ->putJson("/api/diagrams/{$this->diagram->id}", ['schema' => $schema])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        $this->assertCount(6000, $this->diagram->refresh()->schema);
    }

    public function test_update_rejects_non_array_schema(): void
    {
        $this->auth()
            ->putJson("/api/diagrams/{$this->diagram->id}", ['schema' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schema');
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

    public function test_chunked_import_upload_stores_chunks_and_queues_import(): void
    {
        Queue::fake();
        Event::fake();
        Storage::fake('imports');

        $upload = $this->auth()
            ->postJson("/api/diagrams/{$this->diagram->id}/imports", [
                'format' => 'sql',
                'size' => 36,
                'chunk_size' => 18,
                'chunks_total' => 2,
                'original_name' => 'schema.sql',
            ])
            ->assertCreated()
            ->assertJsonPath('status', DiagramImport::STATUS_UPLOADING)
            ->json();

        $importId = $upload['id'];

        $this->auth()
            ->call(
                'PUT',
                "/api/diagrams/{$this->diagram->id}/imports/{$importId}/chunks/0",
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/octet-stream'],
                '123456789012345678'
            )
            ->assertOk()
            ->assertJsonPath('received', 1)
            ->assertJsonPath('complete', false);

        $this->auth()
            ->call(
                'PUT',
                "/api/diagrams/{$this->diagram->id}/imports/{$importId}/chunks/1",
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/octet-stream'],
                'abcdefghijklmnopqr'
            )
            ->assertOk()
            ->assertJsonPath('received', 2)
            ->assertJsonPath('complete', true);

        $this->auth()
            ->postJson("/api/diagrams/{$this->diagram->id}/imports/{$importId}/complete")
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');

        $import = DiagramImport::findOrFail($importId);
        $this->assertSame(DiagramImport::STATUS_UPLOADED, $import->status);
        $this->assertNotNull($import->path);
        Storage::disk('imports')->assertExists($import->path);

        $this->diagram->refresh();
        $this->assertNull($this->diagram->script);
        $this->assertSame(ImportStatus::PENDING, $this->diagram->import_status);
    }

    public function test_chunked_import_rejects_missing_chunks(): void
    {
        Queue::fake();
        Storage::fake('imports');

        $importId = $this->auth()
            ->postJson("/api/diagrams/{$this->diagram->id}/imports", [
                'format' => 'sql',
                'size' => 10,
                'chunk_size' => 5,
                'chunks_total' => 2,
            ])
            ->assertCreated()
            ->json('id');

        $this->auth()
            ->call(
                'PUT',
                "/api/diagrams/{$this->diagram->id}/imports/{$importId}/chunks/0",
                [],
                [],
                [],
                ['CONTENT_TYPE' => 'application/octet-stream'],
                '12345'
            )
            ->assertOk();

        $this->auth()
            ->postJson("/api/diagrams/{$this->diagram->id}/imports/{$importId}/complete")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Import is missing one or more chunks.');
    }

    public function test_chunked_import_rejects_files_larger_than_two_gigabytes(): void
    {
        Storage::fake('imports');

        $this->auth()
            ->postJson("/api/diagrams/{$this->diagram->id}/imports", [
                'format' => 'sql',
                'size' => 2147483649,
                'chunk_size' => 16777216,
                'chunks_total' => 129,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Import file must be between 1 byte and 2GB.');
    }

    public function test_chunked_import_requires_owner_access(): void
    {
        Storage::fake('imports');
        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/diagrams/{$this->diagram->id}/imports", [
                'format' => 'sql',
                'size' => 10,
                'chunk_size' => 10,
                'chunks_total' => 1,
            ])
            ->assertForbidden();
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
