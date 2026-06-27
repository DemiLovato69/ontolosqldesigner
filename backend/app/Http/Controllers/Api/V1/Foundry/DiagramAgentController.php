<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Foundry;

use App\Exceptions\FoundryException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DiagramAgentMessageResource;
use App\Http\Resources\DiagramAgentSessionResource;
use App\Models\Diagram;
use App\Models\DiagramAgentMessage;
use App\Models\DiagramAgentSession;
use App\Services\Foundry\DiagramAgentService;
use App\Services\Foundry\FoundryHostConfigService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Group;

/**
 * Diagram agent sessions and messages. Sessions are shared across diagram
 * collaborators (read access to view, write access to use) and are archived
 * rather than deleted.
 */
#[Group('Foundry')]
class DiagramAgentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DiagramAgentService $agent,
        private readonly FoundryHostConfigService $hosts,
    ) {}

    public function index(Request $request, Diagram $diagram): AnonymousResourceCollection
    {
        $this->authorize('viewAgent', $diagram);

        $query = DiagramAgentSession::query()
            ->where('diagram_id', $diagram->id)
            ->with('creator');

        if (! $request->boolean('include_archived')) {
            $query->active();
        }

        $sessions = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        return DiagramAgentSessionResource::collection($sessions);
    }

    public function store(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureEnabled();
        $this->ensureOntology($diagram);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
        ]);

        $session = DiagramAgentSession::create([
            'diagram_id' => $diagram->id,
            'created_by_user_id' => $request->user()->id,
            'foundry_host_url' => $this->resolveHostOrNull($diagram),
            'title' => isset($validated['title']) ? trim((string) $validated['title']) ?: null : null,
            'model' => $validated['model'] ?? $this->agent->defaultModel($diagram)?->model,
            'status' => DiagramAgentSession::STATUS_ACTIVE,
        ]);

        return (new DiagramAgentSessionResource($session->load('creator')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Diagram $diagram, DiagramAgentSession $session): DiagramAgentSessionResource
    {
        $this->authorize('viewAgent', $diagram);
        $this->ensureBelongs($diagram, $session);

        $session->load(['creator', 'messages' => fn ($query) => $query->with('user')->orderBy('id')]);

        return new DiagramAgentSessionResource($session);
    }

    public function update(Request $request, Diagram $diagram, DiagramAgentSession $session): DiagramAgentSessionResource
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureBelongs($diagram, $session);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $session->update(['title' => trim((string) $validated['title']) ?: null]);

        return new DiagramAgentSessionResource($session->load('creator'));
    }

    public function archive(Request $request, Diagram $diagram, DiagramAgentSession $session): DiagramAgentSessionResource
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureBelongs($diagram, $session);

        $session->update([
            'status' => DiagramAgentSession::STATUS_ARCHIVED,
            'archived_at' => now(),
            'archived_by_user_id' => $request->user()->id,
        ]);

        return new DiagramAgentSessionResource($session->load('creator'));
    }

    public function unarchive(Diagram $diagram, DiagramAgentSession $session): DiagramAgentSessionResource
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureBelongs($diagram, $session);

        $session->update([
            'status' => DiagramAgentSession::STATUS_ACTIVE,
            'archived_at' => null,
            'archived_by_user_id' => null,
        ]);

        return new DiagramAgentSessionResource($session->load('creator'));
    }

    public function message(Request $request, Diagram $diagram, DiagramAgentSession $session): JsonResponse
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureBelongs($diagram, $session);

        if ($session->isArchived()) {
            return $this->success([
                'error' => [
                    'code' => 'foundry_llm_session_archived',
                    'message' => 'This session is archived. Unarchive it to continue.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:8000'],
            'model' => ['nullable', 'string', 'max:120'],
            'allow_destructive' => ['nullable', 'boolean'],
        ]);

        $assistant = $this->agent->sendMessage(
            $request->user(),
            $diagram,
            $session,
            trim((string) $validated['message']),
            isset($validated['model']) ? (string) $validated['model'] : null,
            (bool) ($validated['allow_destructive'] ?? false),
        );

        return (new DiagramAgentMessageResource($assistant->load('user')))
            ->response()
            ->setStatusCode(200);
    }

    public function markApplied(Request $request, Diagram $diagram, DiagramAgentSession $session, DiagramAgentMessage $message): DiagramAgentMessageResource
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureBelongs($diagram, $session);
        $this->ensureMessageBelongs($session, $message);

        $message->update([
            'applied_at' => now(),
            'applied_by_user_id' => $request->user()->id,
        ]);

        return new DiagramAgentMessageResource($message->load('user'));
    }

    public function unmarkApplied(Diagram $diagram, DiagramAgentSession $session, DiagramAgentMessage $message): DiagramAgentMessageResource
    {
        $this->authorize('useAgent', $diagram);
        $this->ensureBelongs($diagram, $session);
        $this->ensureMessageBelongs($session, $message);

        $message->update([
            'applied_at' => null,
            'applied_by_user_id' => null,
        ]);

        return new DiagramAgentMessageResource($message->load('user'));
    }

    private function ensureBelongs(Diagram $diagram, DiagramAgentSession $session): void
    {
        if ($session->diagram_id !== $diagram->id) {
            abort(404);
        }
    }

    private function ensureMessageBelongs(DiagramAgentSession $session, DiagramAgentMessage $message): void
    {
        if ($message->session_id !== $session->id) {
            abort(404);
        }
    }

    private function ensureEnabled(): void
    {
        if (! $this->agent->isEnabled()) {
            throw FoundryException::llmDisabled();
        }
    }

    private function ensureOntology(Diagram $diagram): void
    {
        if (! $diagram->isOntology()) {
            throw FoundryException::diagramNotOntology();
        }
    }

    private function resolveHostOrNull(Diagram $diagram): ?string
    {
        $host = $diagram->foundryConfig?->host_url;
        if (! is_string($host) || $host === '') {
            return null;
        }

        try {
            return $this->hosts->normalize($host);
        } catch (FoundryException) {
            return null;
        }
    }
}
