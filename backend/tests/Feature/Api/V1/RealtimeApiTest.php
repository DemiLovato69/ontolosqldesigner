<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Diagram;
use App\Models\User;
use Tests\TestCase;

class RealtimeApiTest extends TestCase
{
    public function test_bearer_token_can_fetch_realtime_config(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('Dioxus Desktop', ['desktop', 'presence:read'], now()->addHour());

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/realtime/config')
            ->assertOk()
            ->assertJsonPath('data.driver', 'reverb')
            ->assertJsonPath('data.auth_endpoint', rtrim((string) config('app.url'), '/').'/api/v1/broadcasting/auth');
    }

    public function test_bearer_token_can_authorize_reverb_presence_channels(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $diagram = Diagram::factory()->create(['user_id' => $owner->id]);
        $token = $owner->createToken('Dioxus Desktop', ['desktop', 'presence:read', 'presence:write'], now()->addHour());

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "presence-diagram.{$diagram->share_token}",
            ])
            ->assertOk();

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "presence-diagram.{$diagram->share_token}.writers",
            ])
            ->assertOk();
    }
}
