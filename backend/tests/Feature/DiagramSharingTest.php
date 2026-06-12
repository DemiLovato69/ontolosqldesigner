<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramVisitor;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DiagramSharingTest extends TestCase
{

    public function test_visitor_request_is_pending_when_approval_required(): void
    {
        Event::fake();

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $visitor = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'per_user',
            'require_approval' => true,
        ]);

        $this->actingAs($visitor, 'sanctum')
            ->getJson('/api/diagrams/shared/'.$diagram->share_token)
            ->assertStatus(403)
            ->assertJsonFragment(['pending_approval' => true]);

        $this->assertDatabaseHas('diagram_visitors', [
            'diagram_id' => $diagram->id,
            'user_id' => $visitor->id,
            'status' => 'pending',
        ]);
    }

    public function test_visitor_can_read_after_owner_approves(): void
    {
        Event::fake();

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $visitor = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'per_user',
            'require_approval' => true,
            'schema' => [],
        ]);

        // Visitor requests access — creates a pending visitor record
        $this->actingAs($visitor, 'sanctum')
            ->getJson('/api/diagrams/shared/'.$diagram->share_token)
            ->assertStatus(403);

        $visitorRecord = DiagramVisitor::where('diagram_id', $diagram->id)
            ->where('user_id', $visitor->id)
            ->firstOrFail();

        // Owner approves the visitor
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/diagrams/{$diagram->id}/visitors/{$visitorRecord->id}/approve")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        // Visitor can now access the diagram
        $this->actingAs($visitor, 'sanctum')
            ->getJson('/api/diagrams/shared/'.$diagram->share_token)
            ->assertStatus(200);
    }

    public function test_visitor_can_write_after_write_access_granted(): void
    {
        Event::fake();

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $visitor = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'per_user',
            'require_approval' => true,
            'schema' => [],
        ]);

        // Visitor requests access
        $this->actingAs($visitor, 'sanctum')
            ->getJson('/api/diagrams/shared/'.$diagram->share_token);

        $visitorRecord = DiagramVisitor::where('diagram_id', $diagram->id)
            ->where('user_id', $visitor->id)
            ->firstOrFail();

        // Owner grants write access (also sets status to approved)
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/diagrams/{$diagram->id}/visitors/{$visitorRecord->id}", ['access' => 'write'])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);

        // Visitor can save schema via shared token
        $this->actingAs($visitor, 'sanctum')
            ->patchJson('/api/diagrams/shared/'.$diagram->share_token, ['schema' => [['id' => 'node1']]])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);
    }
}
