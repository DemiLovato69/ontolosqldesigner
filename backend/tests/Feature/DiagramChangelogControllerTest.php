<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramVisitor;
use App\Models\User;
use Tests\TestCase;

class DiagramChangelogControllerTest extends TestCase
{

    public function test_index_returns_changelog(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/diagrams/{$diagram->id}/changelog")
            ->assertStatus(200);
    }

    public function test_store_creates_entry(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/diagrams/{$diagram->id}/changelog", ['action' => 'save'])
            ->assertStatus(201)
            ->assertJson(['status' => true]);
    }

    public function test_read_only_shared_user_cannot_create_changelog_entry(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $reader = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => 'read']);

        $this->actingAs($reader, 'sanctum')
            ->postJson("/api/diagrams/{$diagram->id}/changelog", ['action' => 'save'])
            ->assertStatus(403);
    }

    public function test_shared_writer_can_create_changelog_entry(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => 'write']);

        $this->actingAs($writer, 'sanctum')
            ->postJson("/api/diagrams/{$diagram->id}/changelog", ['action' => 'save'])
            ->assertStatus(201);
    }

    public function test_revoked_shared_writer_cannot_create_changelog_entry(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $writer = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => 'write']);
        DiagramVisitor::factory()->create([
            'diagram_id' => $diagram->id,
            'user_id' => $writer->id,
            'status' => 'revoked',
        ]);

        $this->actingAs($writer, 'sanctum')
            ->postJson("/api/diagrams/{$diagram->id}/changelog", ['action' => 'save'])
            ->assertStatus(403);
    }
}
