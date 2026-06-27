<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoundryLlmModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoundryLlmModel>
 */
class FoundryLlmModelFactory extends Factory
{
    public function definition(): array
    {
        $model = fake()->randomElement(['gpt-4o', 'gpt-4.1', 'gpt-4o-mini']);

        return [
            'host_url' => null,
            'provider' => 'openai',
            'model' => $model,
            'display_name' => strtoupper($model),
            'description' => null,
            'enabled' => true,
            'is_default' => false,
            'max_output_tokens' => null,
            'temperature' => null,
            'sort_order' => 0,
        ];
    }

    public function forHost(string $hostUrl): static
    {
        return $this->state(fn (): array => ['host_url' => $hostUrl]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
