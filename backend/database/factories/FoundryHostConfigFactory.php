<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoundryHostConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoundryHostConfig>
 */
class FoundryHostConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host_url' => 'https://'.fake()->domainWord().'.palantirfoundry.com',
            'display_name' => fake()->company(),
            'client_id' => fake()->uuid(),
            'client_secret' => null,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }

    public function confidential(): static
    {
        return $this->state(fn (): array => ['client_secret' => 'secret-'.fake()->sha256()]);
    }
}
