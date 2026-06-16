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

    public function test_shared_save_requires_array_schema(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $user->id,
            'share_access' => 'write',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/diagrams/shared/'.$diagram->share_token, ['schema' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schema');
    }

    public function test_public_library_diagram_is_readable_even_if_previously_per_user_with_approval(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $visitor = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'per_user',
            'library' => true,
            'require_approval' => true,
            'schema' => [],
        ]);

        $this->actingAs($visitor, 'sanctum')
            ->getJson('/api/diagrams/shared/'.$diagram->share_token)
            ->assertStatus(200)
            ->assertJsonPath('data.share_access', 'read')
            ->assertJsonPath('data.library', true);

        $diagram->refresh();

        $this->assertSame('read', $diagram->share_access->value);
        $this->assertFalse((bool) $diagram->require_approval);
    }

    public function test_enabling_public_library_normalizes_to_read_without_approval(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'per_user',
            'require_approval' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/diagrams/{$diagram->id}/share", ['library' => true])
            ->assertStatus(200)
            ->assertJsonPath('share_access', 'read')
            ->assertJsonPath('require_approval', false)
            ->assertJsonPath('library', true);

        $diagram->refresh();

        $this->assertSame('read', $diagram->share_access->value);
        $this->assertFalse((bool) $diagram->require_approval);
    }

    public function test_public_library_diagram_cannot_be_changed_to_per_user_or_require_approval(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'read',
            'library' => true,
            'require_approval' => false,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/diagrams/{$diagram->id}/share", ['access' => 'per_user'])
            ->assertStatus(200)
            ->assertJsonPath('share_access', 'read')
            ->assertJsonPath('require_approval', false);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/diagrams/{$diagram->id}/share", ['require_approval' => true])
            ->assertStatus(200)
            ->assertJsonPath('share_access', 'read')
            ->assertJsonPath('require_approval', false);

        $diagram->refresh();

        $this->assertSame('read', $diagram->share_access->value);
        $this->assertFalse((bool) $diagram->require_approval);
    }

    public function test_public_library_diagram_can_still_be_explicitly_editable(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'read',
            'library' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/diagrams/{$diagram->id}/share", ['access' => 'write'])
            ->assertStatus(200)
            ->assertJsonPath('share_access', 'write')
            ->assertJsonPath('require_approval', false);
    }

    public function test_owner_can_share_with_existing_or_arbitrary_email(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => 'read']);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/diagrams/{$diagram->id}/invites", [
                'invites' => [
                    ['email' => 'Coworker@Example.com', 'access' => 'read'],
                    ['email' => 'external@example.test', 'access' => 'write'],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('0.email', 'coworker@example.com')
            ->assertJsonPath('1.email', 'external@example.test');

        $this->assertDatabaseHas('diagram_invites', [
            'diagram_id' => $diagram->id,
            'email' => 'coworker@example.com',
            'access' => 'read',
        ]);
    }

    public function test_invited_user_can_access_and_write_when_granted(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $invited = User::factory()->create(['email' => 'writer@example.com', 'email_verified_at' => now()]);
        $diagram = Diagram::factory()->create([
            'user_id' => $owner->id,
            'share_access' => 'read',
            'schema' => [],
        ]);
        $diagram->invites()->create(['email' => 'writer@example.com', 'access' => 'write']);

        $this->actingAs($invited, 'sanctum')
            ->getJson('/api/diagrams/shared/'.$diagram->share_token)
            ->assertStatus(200)
            ->assertJsonPath('data.share_access', 'write');

        $this->actingAs($invited, 'sanctum')
            ->patchJson('/api/diagrams/shared/'.$diagram->share_token, ['schema' => [['id' => 'node1']]])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true]);
    }

    public function test_share_user_search_autocompletes_existing_emails(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        User::factory()->create(['email' => 'alice@example.com', 'email_verified_at' => now()]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/diagrams/share-users/search?q=ali')
            ->assertStatus(200)
            ->assertJsonFragment(['alice@example.com']);
    }
}
