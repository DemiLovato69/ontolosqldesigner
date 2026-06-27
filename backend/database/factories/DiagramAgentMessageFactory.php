<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\DiagramAgentMessage;
use App\Models\DiagramAgentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagramAgentMessage>
 */
class DiagramAgentMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => DiagramAgentSession::factory(),
            'diagram_id' => Diagram::factory(),
            'user_id' => User::factory(),
            'role' => DiagramAgentMessage::ROLE_ASSISTANT,
            'model' => 'gpt-4o',
            'prompt' => null,
            'response' => 'Here is what I suggest.',
            'patch' => ['operations' => []],
            'warnings' => [],
            'context_summary' => ['table_count' => 0],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20],
            'status' => DiagramAgentMessage::STATUS_COMPLETED,
            'error_code' => null,
            'error_message' => null,
        ];
    }

    public function user(string $prompt = 'Add a customer table'): static
    {
        return $this->state(fn (): array => [
            'role' => DiagramAgentMessage::ROLE_USER,
            'prompt' => $prompt,
            'response' => null,
            'patch' => null,
            'context_summary' => null,
            'usage' => null,
        ]);
    }

    public function failed(string $code = 'foundry_llm_invalid_response'): static
    {
        return $this->state(fn (): array => [
            'status' => DiagramAgentMessage::STATUS_FAILED,
            'patch' => null,
            'error_code' => $code,
            'error_message' => 'The model returned an unexpected response.',
        ]);
    }
}
