<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_register_returns_token(): void
    {
        $this->postJson('/api/register', [
            'email'    => 'reg_' . uniqid() . '@example.com',
            'password' => 'Secret1!',
        ])->assertStatus(200)->assertJsonFragment(['status' => true])->assertJsonStructure(['token']);
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret1!')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret1!'])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true])
            ->assertJsonStructure(['token']);
    }

    public function test_logout_succeeds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_resend_verification_succeeds(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/resend')
            ->assertStatus(200)
            ->assertJsonStructure(['message']);
    }
}
