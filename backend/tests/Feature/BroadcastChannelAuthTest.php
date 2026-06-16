<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\DiagramVisitor;
use App\Models\User;
use Tests\TestCase;

class BroadcastChannelAuthTest extends TestCase
{
    public function test_guest_broadcast_auth_does_not_error_when_login_route_is_absent(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-diagram.missing',
        ])->assertUnauthorized();
    }

    public function test_owner_can_join_read_and_writer_channels(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id]);

        $this->broadcastAuth($owner, "presence-diagram.{$diagram->share_token}")
            ->assertOk();
        $this->broadcastAuth($owner, "presence-diagram.{$diagram->share_token}.writers")
            ->assertOk();
    }

    public function test_read_only_user_can_join_read_channel_but_not_writer_channel(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $reader = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => 'read']);

        $this->broadcastAuth($reader, "presence-diagram.{$diagram->share_token}")
            ->assertOk();
        $this->broadcastAuth($reader, "presence-diagram.{$diagram->share_token}.writers")
            ->assertForbidden();
    }

    public function test_revoked_user_cannot_join_read_or_writer_channel(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => 'write']);
        DiagramVisitor::factory()->create([
            'diagram_id' => $diagram->id,
            'user_id' => $user->id,
            'status' => 'revoked',
        ]);

        $this->broadcastAuth($user, "presence-diagram.{$diagram->share_token}")
            ->assertForbidden();
        $this->broadcastAuth($user, "presence-diagram.{$diagram->share_token}.writers")
            ->assertForbidden();
    }

    public function test_random_user_cannot_join_private_diagram_channel(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id, 'share_access' => null]);

        $this->broadcastAuth($user, "presence-diagram.{$diagram->share_token}")
            ->assertForbidden();
    }

    private function broadcastAuth(User $user, string $channelName): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => $channelName,
            ]);
    }
}
