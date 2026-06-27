<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use App\Models\Diagram;
use App\Models\DiagramAgentMessage;
use App\Models\DiagramAgentSession;
use App\Models\FoundryLlmModel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Orchestrates a diagram agent turn: resolve the model + the user's Foundry
 * connection, build the full-diagram context and prompt, call the Foundry AIP
 * LLM proxy, validate the patch, and persist the prompt/response for the shared
 * (collaborator-visible) session history.
 */
class DiagramAgentService
{
    public function __construct(
        private readonly FoundryPlatformService $platform,
        private readonly FoundryConnectionService $connections,
        private readonly FoundryHostConfigService $hosts,
        private readonly FoundryLlmClient $llm,
        private readonly DiagramAgentContextBuilder $contextBuilder,
        private readonly DiagramAgentPromptBuilder $promptBuilder,
        private readonly DiagramAgentPatchValidator $validator,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('foundry.llm.enabled', false);
    }

    /**
     * Enabled models available for a diagram's Foundry host (host-specific plus
     * global). Returns an empty collection when the agent is disabled.
     *
     * @return Collection<int, FoundryLlmModel>
     */
    public function availableModels(Diagram $diagram): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return FoundryLlmModel::query()
            ->enabled()
            ->forHost($this->resolveHostOrNull($diagram))
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('model')
            ->get();
    }

    public function defaultModel(Diagram $diagram): ?FoundryLlmModel
    {
        $models = $this->availableModels($diagram);

        return $models->firstWhere('is_default', true) ?? $models->first();
    }

    /**
     * Run one agent turn against a session and return the assistant message.
     *
     * @throws FoundryException
     */
    public function sendMessage(
        User $user,
        Diagram $diagram,
        DiagramAgentSession $session,
        string $message,
        ?string $modelId,
        bool $allowDestructive = false,
    ): DiagramAgentMessage {
        if (! $this->isEnabled()) {
            throw FoundryException::llmDisabled();
        }

        // Foundry agent requires an ontology diagram with a configured host.
        $host = $this->platform->requireDiagramHost($diagram);
        $model = $this->resolveModel($host, $modelId);

        $built = $this->contextBuilder->build($diagram);
        if ($built['bytes'] > (int) config('foundry.llm.max_context_bytes', 250000)) {
            throw FoundryException::llmContextTooLarge();
        }

        // Resolve the caller's own token first so a missing/expired connection
        // fails cleanly before we persist anything.
        $accessToken = $this->connections->freshAccessToken($user, $host);

        $storePrompts = (bool) config('foundry.llm.store_prompts', true);

        $this->recordUserMessage($session, $diagram, $user, $message, $model->model, $storePrompts);

        $messages = $this->promptBuilder->build($session, $built['context'], $message, $allowDestructive);

        $payload = [
            'model' => $model->model,
            'messages' => $messages,
            'temperature' => $model->temperature ?? (float) config('foundry.llm.temperature', 0.1),
            'max_tokens' => $model->max_output_tokens ?? (int) config('foundry.llm.max_output_tokens', 4000),
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = $this->llm->chatCompletion($host, $accessToken, $payload);
            $content = $this->extractContent($response);
            $parsed = $this->validator->parse($content, $allowDestructive);

            $assistant = $this->recordAssistantMessage($session, $diagram, $user, $model->model, [
                'response' => $storePrompts ? $parsed['message'] : null,
                'patch' => ['operations' => $parsed['operations']],
                'warnings' => $parsed['warnings'],
                'context_summary' => $built['summary'],
                'usage' => $this->extractUsage($response),
                'status' => DiagramAgentMessage::STATUS_COMPLETED,
            ]);
        } catch (FoundryException $exception) {
            $this->recordAssistantMessage($session, $diagram, $user, $model->model, [
                'response' => null,
                'patch' => null,
                'warnings' => [],
                'context_summary' => $built['summary'],
                'usage' => null,
                'status' => DiagramAgentMessage::STATUS_FAILED,
                'error_code' => $exception->errorCode,
                'error_message' => $exception->getMessage(),
            ]);

            $this->touchSession($session, $model->model);

            throw $exception;
        }

        $this->touchSession($session, $model->model);

        return $assistant;
    }

    /**
     * @throws FoundryException
     */
    private function resolveModel(string $host, ?string $modelId): FoundryLlmModel
    {
        $base = FoundryLlmModel::query()->enabled()->forHost($host);

        if ($modelId !== null && $modelId !== '') {
            $model = (clone $base)->where('model', $modelId)->first();
            if (! $model) {
                throw FoundryException::llmModelNotAllowed();
            }

            return $model;
        }

        $default = (clone $base)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('model')->first();
        if (! $default) {
            throw FoundryException::llmModelRequired();
        }

        return $default;
    }

    private function recordUserMessage(
        DiagramAgentSession $session,
        Diagram $diagram,
        User $user,
        string $message,
        string $model,
        bool $storePrompts,
    ): DiagramAgentMessage {
        return DiagramAgentMessage::create([
            'session_id' => $session->id,
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'role' => DiagramAgentMessage::ROLE_USER,
            'model' => $model,
            'prompt' => $storePrompts ? $message : null,
            'status' => DiagramAgentMessage::STATUS_COMPLETED,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function recordAssistantMessage(
        DiagramAgentSession $session,
        Diagram $diagram,
        User $user,
        string $model,
        array $attributes,
    ): DiagramAgentMessage {
        return DiagramAgentMessage::create(array_merge([
            'session_id' => $session->id,
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'role' => DiagramAgentMessage::ROLE_ASSISTANT,
            'model' => $model,
        ], $attributes));
    }

    private function touchSession(DiagramAgentSession $session, string $model): void
    {
        $session->forceFill([
            'last_message_at' => now(),
            'model' => $model,
        ]);

        if (($session->title === null || $session->title === '') && $session->exists) {
            $first = $session->messages()->where('role', DiagramAgentMessage::ROLE_USER)->orderBy('id')->first();
            if ($first && is_string($first->prompt) && $first->prompt !== '') {
                $session->title = Str::limit(trim($first->prompt), 60);
            }
        }

        $session->save();
    }

    /**
     * @param array<string, mixed> $response
     *
     * @throws FoundryException
     */
    private function extractContent(array $response): string
    {
        $content = $response['choices'][0]['message']['content'] ?? null;

        if (is_array($content)) {
            // Some providers return content as an array of text parts.
            $content = collect($content)
                ->map(fn ($part) => is_array($part) ? ($part['text'] ?? '') : (string) $part)
                ->implode('');
        }

        if (! is_string($content) || trim($content) === '') {
            throw FoundryException::llmInvalidResponse('The model returned an empty response.');
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>|null
     */
    private function extractUsage(array $response): ?array
    {
        $usage = $response['usage'] ?? null;

        return is_array($usage) ? $usage : null;
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
