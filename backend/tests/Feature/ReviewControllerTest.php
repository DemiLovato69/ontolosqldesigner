<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_check_review_returns_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/review')
            ->assertStatus(200)
            ->assertJsonStructure(['reviewed']);
    }

    public function test_store_review_returns_ok(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/review', ['stars' => 5, 'message' => 'Great tool!'])
            ->assertStatus(200)
            ->assertJson(['status' => true]);
    }
}
