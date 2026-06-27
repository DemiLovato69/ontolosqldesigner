<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FoundryConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoundryConnection>
 */
class FoundryConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'host_url' => 'https://'.fake()->domainWord().'.palantirfoundry.com',
            'client_id' => fake()->uuid(),
            'foundry_user_id' => fake()->uuid(),
            'display_name' => fake()->name(),
            'scopes' => ['api:read-data', 'offline_access'],
            'access_token' => 'access-'.fake()->sha256(),
            'refresh_token' => 'refresh-'.fake()->sha256(),
            'expires_at' => now()->addHour(),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now(), 'access_token' => null]);
    }

    public function withoutRefreshToken(): static
    {
        return $this->state(fn (): array => ['refresh_token' => null]);
    }
}
