<?php

namespace Database\Factories;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Diagram>
 */
class DiagramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->word(),
            'schema'      => null,
            'script'      => null,
            'user_id'     => User::factory(),
            'share_token' => (string) Str::uuid(),
        ];
    }
}