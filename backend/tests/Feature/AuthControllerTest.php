<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{

    public function test_registration_endpoint_is_not_available(): void
    {
        $this->postJson('/api/register', [
            'email' => 'reg_'.uniqid().'@example.com',
            'password' => 'Secret1!',
        ])->assertMethodNotAllowed();
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

    public function test_login_fails_with_wrong_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-pass')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-pass'])
            ->assertStatus(401)
            ->assertJsonFragment(['status' => false]);
    }

    public function test_oauth_routes_are_not_available(): void
    {
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/github')->assertNotFound();
        $this->get('/auth/gitlab')->assertNotFound();
    }
}
