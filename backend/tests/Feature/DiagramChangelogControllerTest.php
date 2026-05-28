<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DiagramChangelogControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_returns_changelog(): void
    {
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/diagrams/{$diagram->id}/changelog")
            ->assertStatus(200);
    }

    public function test_store_creates_entry(): void
    {
        $user    = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/diagrams/{$diagram->id}/changelog", ['action' => 'save'])
            ->assertStatus(201)
            ->assertJson(['status' => true]);
    }
}
