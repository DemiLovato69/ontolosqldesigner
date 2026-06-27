<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\DiagramAgentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiagramAgentSession>
 */
class DiagramAgentSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'diagram_id' => Diagram::factory(),
            'created_by_user_id' => User::factory(),
            'foundry_host_url' => 'https://acme.palantirfoundry.com',
            'title' => fake()->sentence(3),
            'model' => 'gpt-4o',
            'status' => DiagramAgentSession::STATUS_ACTIVE,
            'last_message_at' => now(),
            'archived_at' => null,
            'archived_by_user_id' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => DiagramAgentSession::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);
    }
}
