<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Foundry;

use App\Models\Diagram;
use App\Models\DiagramFoundryConfig;
use App\Models\DiagramVisitor;
use App\Models\FoundryConnection;
use App\Models\User;
use App\Services\Foundry\FoundryRuntimeClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FoundryApiTest extends TestCase
{
    private const ABILITIES = [
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
        'foundry:connect',
        'foundry:read',
        'tokens:manage',
    ];

    private const HOST = 'https://acme.palantirfoundry.com';

    private User $owner;

    private Diagram $diagram;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('foundry.hosts', [
            self::HOST => ['client_id' => 'client-123', 'client_secret' => 'secret-xyz', 'display_name' => 'Acme'],
        ]);
        config()->set('foundry.allow_custom_hosts', false);
        config()->set('foundry.allow_insecure_hosts', false);
        config()->set('foundry.default_scopes', ['api:read-data', 'offline_access']);
        config()->set('foundry.redirect_uri', 'https://app.test/api/v1/foundry/oauth/callback');

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->diagram = Diagram::factory()->create([
            'user_id' => $this->owner->id,
            'db_type' => 'ontology',
            'schema' => [],
        ]);
    }

    public function test_owner_can_set_and_read_foundry_host(): void
    {
        $this->withDesktopToken($this->owner)
            ->putJson("/api/v1/diagrams/{$this->diagram->id}/foundry/config", [
                'host_url' => 'acme.palantirfoundry.com',
                'default_folder_rid' => 'ri.compass.main.folder.abc',
            ])
            ->assertOk()
            ->assertJsonPath('data.host_url', self::HOST)
            ->assertJsonPath('data.default_folder_rid', 'ri.compass.main.folder.abc');

        $this->assertDatabaseHas('diagram_foundry_configs', [
            'diagram_id' => $this->diagram->id,
            'host_url' => self::HOST,
        ]);

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/config")
            ->assertOk()
            ->assertJsonPath('data.host_url', self::HOST);
    }

    public function test_non_owner_with_write_access_cannot_change_host(): void
    {
        $shared = Diagram::factory()->create([
            'user_id' => $this->owner->id,
            'db_type' => 'ontology',
            'share_access' => 'write',
        ]);
        $collaborator = User::factory()->create(['email_verified_at' => now()]);
        DiagramVisitor::factory()->create([
            'diagram_id' => $shared->id,
            'user_id' => $collaborator->id,
            'status' => 'approved',
            'access' => 'write',
        ]);

        $this->withDesktopToken($collaborator)
            ->putJson("/api/v1/diagrams/{$shared->id}/foundry/config", ['host_url' => self::HOST])
            ->assertForbidden();

        $this->assertDatabaseMissing('diagram_foundry_configs', ['diagram_id' => $shared->id]);
    }

    public function test_config_rejected_for_non_ontology_diagram(): void
    {
        $mysql = Diagram::factory()->create(['user_id' => $this->owner->id, 'db_type' => 'mysql']);

        $this->withDesktopToken($this->owner)
            ->putJson("/api/v1/diagrams/{$mysql->id}/foundry/config", ['host_url' => self::HOST])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_diagram_not_ontology');
    }

    public function test_connection_status_transitions(): void
    {
        // No config row yet.
        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/connection-status")
            ->assertOk()
            ->assertJsonPath('data.state', 'host_not_set');

        DiagramFoundryConfig::factory()->create([
            'diagram_id' => $this->diagram->id,
            'host_url' => self::HOST,
        ]);

        // Host configured but no connection for this user.
        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/connection-status")
            ->assertOk()
            ->assertJsonPath('data.state', 'disconnected')
            ->assertJsonPath('data.configured', true);

        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'expires_at' => now()->addHour(),
        ]);

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/connection-status")
            ->assertOk()
            ->assertJsonPath('data.state', 'connected')
            ->assertJsonPath('data.connected', true);
    }

    public function test_authorize_returns_authorization_url(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);

        $response = $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/oauth/authorize")
            ->assertOk();

        $url = $response->json('data.authorize_url');
        $this->assertStringContainsString(self::HOST.'/multipass/api/oauth2/authorize', $url);
        $this->assertStringContainsString('client_id=client-123', $url);
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertNotEmpty($response->json('data.state'));
    }

    public function test_oauth_callback_stores_per_user_connection_and_redirects(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);

        Http::fake([
            self::HOST.'/multipass/api/oauth2/token' => Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'api:read-data offline_access',
            ]),
        ]);

        $state = $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/oauth/authorize")
            ->json('data.state');

        $this->getJson("/api/v1/foundry/oauth/callback?state={$state}&code=auth-code-123")
            ->assertRedirect();

        $this->assertDatabaseHas('foundry_connections', [
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
        ]);

        $connection = FoundryConnection::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertSame('fresh-access-token', $connection->access_token);
        $this->assertTrue($connection->isActive());

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/multipass/api/oauth2/token')
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'auth-code-123'
                && ! empty($request['code_verifier']);
        });
    }

    public function test_reads_use_callers_own_connection_through_runtime(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'access_token' => 'owner-token',
            'expires_at' => now()->addHour(),
        ]);

        $fake = $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets?folderRid=ri.compass.main.folder.x")
            ->assertOk()
            ->assertJsonPath('echo.operation', 'listDatasets');

        $this->assertCount(1, $fake->calls);
        $this->assertSame(self::HOST, $fake->calls[0]['hostUrl']);
        $this->assertSame('owner-token', $fake->calls[0]['accessToken']);
        $this->assertSame('ri.compass.main.folder.x', $fake->calls[0]['params']['folderRid']);
    }

    public function test_reads_require_a_connection_for_the_requesting_user(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets/ri.foundry.main.dataset.abc")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'foundry_connection_required');
    }

    public function test_reads_require_foundry_read_ability(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);

        $this->withDesktopToken($this->owner, ['desktop', 'diagrams:read'])
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets/ri.foundry.main.dataset.abc")
            ->assertForbidden();
    }

    public function test_runtime_error_codes_map_to_status(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'expires_at' => now()->addHour(),
        ]);
        $this->fakeRuntime('foundry_resource_not_found');

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets/ri.foundry.main.dataset.missing")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'foundry_resource_not_found');
    }

    public function test_expired_connection_is_refreshed_before_reads(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'access_token' => 'stale-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            self::HOST.'/multipass/api/oauth2/token' => Http::response([
                'access_token' => 'refreshed-access',
                'refresh_token' => 'refreshed-refresh',
                'expires_in' => 3600,
            ]),
        ]);

        $fake = $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets/ri.foundry.main.dataset.abc")
            ->assertOk();

        $this->assertSame('refreshed-access', $fake->calls[0]['accessToken']);
        Http::assertSent(fn ($request): bool => ($request['grant_type'] ?? null) === 'refresh_token');

        $connection = FoundryConnection::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertTrue($connection->isActive());
    }

    public function test_expired_connection_without_refresh_token_returns_expired(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->withoutRefreshToken()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'expires_at' => now()->subMinute(),
        ]);
        $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets/ri.foundry.main.dataset.abc")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'foundry_connection_expired');
    }

    public function test_user_can_disconnect_their_connection(): void
    {
        $connection = FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
        ]);

        $this->withDesktopToken($this->owner)
            ->deleteJson("/api/v1/foundry/connections/{$connection->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('foundry_connections', ['id' => $connection->id]);
    }

    public function test_user_cannot_disconnect_another_users_connection(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        $connection = FoundryConnection::factory()->create(['user_id' => $other->id, 'host_url' => self::HOST]);

        $this->withDesktopToken($this->owner)
            ->deleteJson("/api/v1/foundry/connections/{$connection->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('foundry_connections', ['id' => $connection->id]);
    }

    public function test_browse_spaces_folders_and_ontologies_use_runtime(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'access_token' => 'owner-token',
            'expires_at' => now()->addHour(),
        ]);
        $fake = $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/spaces")
            ->assertOk()
            ->assertJsonPath('echo.operation', 'listSpaces');

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/folders?rid=ri.compass.main.folder.x")
            ->assertOk()
            ->assertJsonPath('echo.operation', 'listFolderChildren')
            ->assertJsonPath('echo.params.folderRid', 'ri.compass.main.folder.x');

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/ontologies")
            ->assertOk()
            ->assertJsonPath('echo.operation', 'listOntologies');

        $this->assertSame('owner-token', $fake->calls[0]['accessToken']);
    }

    public function test_folders_requires_a_rid_when_no_default(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'expires_at' => now()->addHour(),
        ]);
        $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/folders")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_parameter_required');
    }

    public function test_owner_can_save_default_ontology_and_project(): void
    {
        $this->withDesktopToken($this->owner)
            ->putJson("/api/v1/diagrams/{$this->diagram->id}/foundry/config", [
                'host_url' => self::HOST,
                'default_ontology_rid' => 'ri.ontology.main.ontology.123',
                'default_project_rid' => 'ri.compass.main.project.456',
                'default_folder_rid' => 'ri.compass.main.folder.789',
            ])
            ->assertOk()
            ->assertJsonPath('data.default_ontology_rid', 'ri.ontology.main.ontology.123')
            ->assertJsonPath('data.default_project_rid', 'ri.compass.main.project.456');

        $this->assertDatabaseHas('diagram_foundry_configs', [
            'diagram_id' => $this->diagram->id,
            'default_ontology_rid' => 'ri.ontology.main.ontology.123',
            'default_project_rid' => 'ri.compass.main.project.456',
            'default_folder_rid' => 'ri.compass.main.folder.789',
        ]);
    }

    public function test_connect_with_token_stores_connection_and_is_used_for_reads(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        $fake = $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/token", ['token' => 'my-foundry-token'])
            ->assertOk()
            ->assertJsonPath('data.state', 'connected')
            ->assertJsonPath('data.auth_type', 'token');

        $connection = FoundryConnection::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertSame('token', $connection->auth_type);
        $this->assertSame('my-foundry-token', $connection->access_token);
        $this->assertNull($connection->refresh_token);

        // The pasted token is used for subsequent reads.
        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/datasets/ri.foundry.main.dataset.abc")
            ->assertOk();

        $readCall = collect($fake->calls)->last();
        $this->assertSame('getDataset', $readCall['operation']);
        $this->assertSame('my-foundry-token', $readCall['accessToken']);
    }

    public function test_connect_with_token_works_for_host_without_oauth_client(): void
    {
        // Host is NOT in the admin OAuth map and custom hosts are disabled.
        config()->set('foundry.hosts', []);
        config()->set('foundry.allow_custom_hosts', false);
        DiagramFoundryConfig::factory()->create([
            'diagram_id' => $this->diagram->id,
            'host_url' => 'https://unconfigured.palantirfoundry.com',
        ]);
        $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/token", ['token' => 'tok-123456'])
            ->assertOk()
            ->assertJsonPath('data.state', 'connected')
            ->assertJsonPath('data.connectable', false)
            ->assertJsonPath('data.auth_type', 'token');
    }

    public function test_connect_with_token_rejects_a_rejected_token(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        $this->fakeRuntime('foundry_access_denied');

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/token", ['token' => 'bad-token-123'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'foundry_access_denied');

        $this->assertDatabaseMissing('foundry_connections', ['user_id' => $this->owner->id, 'host_url' => self::HOST]);
    }

    public function test_token_auth_can_be_disabled(): void
    {
        config()->set('foundry.allow_token_auth', false);
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        $this->fakeRuntime();

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/token", ['token' => 'whatever-token'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_token_auth_disabled');

        $this->assertDatabaseMissing('foundry_connections', ['user_id' => $this->owner->id, 'host_url' => self::HOST]);
    }

    private function fakeRuntime(?string $throwCode = null): FoundryRuntimeClient
    {
        $fake = new class($throwCode) extends FoundryRuntimeClient
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function __construct(private readonly ?string $throwCode) {}

            public function run(string $operation, string $hostUrl, string $accessToken, array $params = []): array
            {
                $this->calls[] = compact('operation', 'hostUrl', 'accessToken', 'params');

                if ($this->throwCode !== null) {
                    throw \App\Exceptions\FoundryException::fromCode($this->throwCode);
                }

                return ['echo' => ['operation' => $operation, 'params' => $params]];
            }
        };

        $this->app->instance(FoundryRuntimeClient::class, $fake);

        return $fake;
    }

    /** @param list<string> $abilities */
    private function withDesktopToken(User $user, array $abilities = self::ABILITIES): static
    {
        $token = $user->createToken('Desktop', $abilities, now()->addHour())->plainTextToken;
        Auth::forgetGuards();

        return $this->withHeader('Accept', 'application/json')->withToken($token);
    }
}
