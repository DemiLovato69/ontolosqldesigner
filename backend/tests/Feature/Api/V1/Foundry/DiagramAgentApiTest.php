<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Foundry;

use App\Models\Diagram;
use App\Models\DiagramAgentMessage;
use App\Models\DiagramAgentSession;
use App\Models\DiagramFoundryConfig;
use App\Models\DiagramVisitor;
use App\Models\FoundryConnection;
use App\Models\FoundryLlmModel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiagramAgentApiTest extends TestCase
{
    private const ABILITIES = [
        'desktop', 'diagrams:read', 'diagrams:write', 'foundry:connect', 'foundry:read', 'foundry:llm',
    ];

    private const HOST = 'https://acme.palantirfoundry.com';

    private const LLM_URL = self::HOST.'/api/v2/llm/proxy/openai/v1/chat/completions';

    private User $owner;

    private Diagram $diagram;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('foundry.llm.enabled', true);
        config()->set('foundry.llm.endpoint', '/api/v2/llm/proxy/openai/v1/chat/completions');
        config()->set('foundry.hosts', [
            self::HOST => ['client_id' => 'client-123', 'display_name' => 'Acme'],
        ]);

        $this->owner = User::factory()->create(['email_verified_at' => now()]);
        $this->diagram = Diagram::factory()->create([
            'user_id' => $this->owner->id,
            'db_type' => 'ontology',
            'schema' => [],
        ]);

        FoundryLlmModel::factory()->create(['model' => 'gpt-4o', 'is_default' => true]);
    }

    public function test_models_endpoint_lists_enabled_models_and_default(): void
    {
        FoundryLlmModel::factory()->disabled()->create(['model' => 'hidden-model']);

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/llm/models")
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('default_model', 'gpt-4o')
            ->assertJsonCount(1, 'data');
    }

    public function test_models_endpoint_reports_disabled_agent(): void
    {
        config()->set('foundry.llm.enabled', false);

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/llm/models")
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonCount(0, 'data');
    }

    public function test_owner_can_create_session_and_send_message(): void
    {
        $this->connectOwner();
        $this->fakeLlm($this->patchResponse());

        $session = $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions", ['model' => 'gpt-4o'])
            ->assertStatus(201)
            ->json('data');

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session['id']}/messages", [
                'message' => 'Add a customer table.',
                'model' => 'gpt-4o',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'assistant')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.patch.operations.0.op', 'add_table');

        // One user + one assistant message persisted.
        $this->assertSame(2, DiagramAgentMessage::where('session_id', $session['id'])->count());

        // The caller's own Foundry token and selected model were used.
        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return str_contains($request->url(), '/api/v2/llm/proxy/openai/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer owner-token')
                && ($body['model'] ?? null) === 'gpt-4o'
                && ($body['response_format']['type'] ?? null) === 'json_object';
        });
    }

    public function test_message_requires_a_connection(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        $session = $this->makeSession();
        $this->fakeLlm($this->patchResponse());

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'foundry_connection_required');

        $this->assertSame(0, DiagramAgentMessage::count());
        Http::assertNothingSent();
    }

    public function test_invalid_model_is_rejected(): void
    {
        $this->connectOwner();
        $session = $this->makeSession();
        $this->fakeLlm($this->patchResponse());

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
                'model' => 'no-such-model',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_llm_model_not_allowed');
    }

    public function test_malformed_model_response_records_failed_message(): void
    {
        $this->connectOwner();
        $session = $this->makeSession();
        $this->fakeLlm(['choices' => [['message' => ['content' => 'I cannot do that.']]]]);

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'foundry_llm_invalid_response');

        $this->assertDatabaseHas('diagram_agent_messages', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'status' => 'failed',
            'error_code' => 'foundry_llm_invalid_response',
        ]);
        // The user prompt is still recorded for the audit trail.
        $this->assertSame(1, DiagramAgentMessage::where('role', 'user')->count());
    }

    public function test_prompts_are_encrypted_at_rest(): void
    {
        $this->connectOwner();
        $session = $this->makeSession();
        $this->fakeLlm($this->patchResponse());

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'SECRET-PROMPT-12345',
            ])
            ->assertOk();

        $raw = DB::table('diagram_agent_messages')->where('role', 'user')->value('prompt');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('SECRET-PROMPT-12345', (string) $raw);

        $message = DiagramAgentMessage::where('role', 'user')->firstOrFail();
        $this->assertSame('SECRET-PROMPT-12345', $message->prompt);
    }

    public function test_context_too_large_is_rejected(): void
    {
        config()->set('foundry.llm.max_context_bytes', 10);
        $this->connectOwner();
        $session = $this->makeSession();
        $this->fakeLlm($this->patchResponse());

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_llm_context_too_large');

        $this->assertSame(0, DiagramAgentMessage::count());
    }

    public function test_disabled_agent_blocks_session_and_messages(): void
    {
        config()->set('foundry.llm.enabled', false);
        $session = $this->makeSession();

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_llm_disabled');

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_llm_disabled');
    }

    public function test_archived_session_rejects_new_messages(): void
    {
        $this->connectOwner();
        $session = $this->makeSession(['status' => DiagramAgentSession::STATUS_ARCHIVED, 'archived_at' => now()]);
        $this->fakeLlm($this->patchResponse());

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_llm_session_archived');
    }

    public function test_archive_hides_session_from_default_list(): void
    {
        $active = $this->makeSession();
        $archived = $this->makeSession();

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$archived->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions?include_archived=1")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$archived->id}/unarchive")
            ->assertOk()
            ->assertJsonPath('data.archived', false);
    }

    public function test_read_only_collaborator_can_view_but_not_send(): void
    {
        $shared = Diagram::factory()->create([
            'user_id' => $this->owner->id,
            'db_type' => 'ontology',
            'share_access' => 'read',
            'schema' => [],
        ]);
        $collaborator = User::factory()->create(['email_verified_at' => now()]);
        $session = DiagramAgentSession::factory()->create([
            'diagram_id' => $shared->id,
            'created_by_user_id' => $this->owner->id,
        ]);

        // Read access can list and view shared sessions.
        $this->withDesktopToken($collaborator)
            ->getJson("/api/v1/diagrams/{$shared->id}/foundry/agent/sessions")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // But cannot send prompts (write required).
        $this->withDesktopToken($collaborator)
            ->postJson("/api/v1/diagrams/{$shared->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertForbidden();
    }

    public function test_message_requires_foundry_llm_ability(): void
    {
        $this->connectOwner();
        $session = $this->makeSession();

        $this->withDesktopToken($this->owner, ['desktop', 'diagrams:read', 'diagrams:write', 'foundry:read'])
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages", [
                'message' => 'Add a table.',
            ])
            ->assertForbidden();
    }

    public function test_message_can_be_marked_applied_and_unapplied(): void
    {
        $session = $this->makeSession();
        $message = DiagramAgentMessage::factory()->create([
            'session_id' => $session->id,
            'diagram_id' => $this->diagram->id,
            'user_id' => $this->owner->id,
        ]);

        // Default state is not applied.
        $this->withDesktopToken($this->owner)
            ->getJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.applied', false);

        // Mark applied (persists across panel reopen so it can't be applied twice).
        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages/{$message->id}/applied")
            ->assertOk()
            ->assertJsonPath('data.applied', true);

        $this->assertNotNull($message->fresh()->applied_at);

        // Unmark (undo) clears it.
        $this->withDesktopToken($this->owner)
            ->deleteJson("/api/v1/diagrams/{$this->diagram->id}/foundry/agent/sessions/{$session->id}/messages/{$message->id}/applied")
            ->assertOk()
            ->assertJsonPath('data.applied', false);

        $this->assertNull($message->fresh()->applied_at);
    }

    public function test_mark_applied_requires_write_access(): void
    {
        $shared = Diagram::factory()->create([
            'user_id' => $this->owner->id,
            'db_type' => 'ontology',
            'share_access' => 'read',
            'schema' => [],
        ]);
        $collaborator = User::factory()->create(['email_verified_at' => now()]);
        $session = DiagramAgentSession::factory()->create([
            'diagram_id' => $shared->id,
            'created_by_user_id' => $this->owner->id,
        ]);
        $message = DiagramAgentMessage::factory()->create([
            'session_id' => $session->id,
            'diagram_id' => $shared->id,
            'user_id' => $this->owner->id,
        ]);

        $this->withDesktopToken($collaborator)
            ->postJson("/api/v1/diagrams/{$shared->id}/foundry/agent/sessions/{$session->id}/messages/{$message->id}/applied")
            ->assertForbidden();
    }

    public function test_non_ontology_diagram_cannot_create_session(): void
    {
        $mysql = Diagram::factory()->create(['user_id' => $this->owner->id, 'db_type' => 'mysql']);

        $this->withDesktopToken($this->owner)
            ->postJson("/api/v1/diagrams/{$mysql->id}/foundry/agent/sessions", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'foundry_diagram_not_ontology');
    }

    private function connectOwner(): void
    {
        DiagramFoundryConfig::factory()->create(['diagram_id' => $this->diagram->id, 'host_url' => self::HOST]);
        FoundryConnection::factory()->create([
            'user_id' => $this->owner->id,
            'host_url' => self::HOST,
            'access_token' => 'owner-token',
            'expires_at' => now()->addHour(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeSession(array $attributes = []): DiagramAgentSession
    {
        return DiagramAgentSession::factory()->create(array_merge([
            'diagram_id' => $this->diagram->id,
            'created_by_user_id' => $this->owner->id,
            'foundry_host_url' => self::HOST,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function patchResponse(): array
    {
        $content = json_encode([
            'message' => 'I suggest adding a Customer table.',
            'patch' => ['operations' => [
                ['op' => 'add_table', 'name' => 'Customer', 'columns' => [['name' => 'id', 'key' => 'PK']]],
            ]],
            'warnings' => [],
        ]);

        return [
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 90, 'total_tokens' => 1290],
        ];
    }

    /** @param array<string, mixed> $response */
    private function fakeLlm(array $response): void
    {
        Http::fake([self::LLM_URL => Http::response($response)]);
    }

    /** @param list<string> $abilities */
    private function withDesktopToken(User $user, array $abilities = self::ABILITIES): static
    {
        $token = $user->createToken('Desktop', $abilities, now()->addHour())->plainTextToken;
        Auth::forgetGuards();

        return $this->withHeader('Accept', 'application/json')->withToken($token);
    }
}
