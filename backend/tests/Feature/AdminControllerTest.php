<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    public function test_login_page_returns_ok(): void
    {
        $this->get('/admin/login')->assertStatus(200);
    }

    public function test_dashboard_redirects_without_auth(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_dashboard_returns_ok(): void
    {
        $this->asAdmin()
            ->get('/admin')
            ->assertStatus(200);
    }

    public function test_library_returns_ok(): void
    {
        $this->asAdmin()
            ->get('/admin/library')
            ->assertStatus(200);
    }

    public function test_reviews_returns_ok(): void
    {
        $this->asAdmin()
            ->get('/admin/reviews')
            ->assertStatus(200);
    }

    public function test_user_activity_returns_ok(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()
            ->getJson("/admin/users/{$user->id}/activity")
            ->assertStatus(200);
    }

    public function test_admin_can_verify_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->asAdmin()
            ->postJson("/admin/users/{$user->id}/verify")
            ->assertOk()
            ->assertJson([
                'verified' => true,
                'changed' => true,
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_admin_can_create_verified_user(): void
    {
        $email = 'internal_'.uniqid().'@example.com';

        $this->asAdmin()
            ->postJson('/admin/users', [
                'email' => $email,
                'password' => 'Secret123',
                'password_confirmation' => 'Secret123',
            ])
            ->assertCreated()
            ->assertJsonPath('email', $email);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_create_user_requires_admin_session(): void
    {
        $this->postJson('/admin/users', [
            'email' => 'blocked@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertRedirect('/admin/login');

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_create_user_validates_unique_email_and_confirmed_password(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()
            ->postJson('/admin/users', [
                'email' => $user->email,
                'password' => 'Secret123',
                'password_confirmation' => 'Different123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_verifying_user_is_idempotent(): void
    {
        $verifiedAt = now()->subDay()->startOfSecond();
        $user = User::factory()->create(['email_verified_at' => $verifiedAt]);

        $this->asAdmin()
            ->postJson("/admin/users/{$user->id}/verify")
            ->assertOk()
            ->assertJson([
                'verified' => true,
                'changed' => false,
            ]);

        $this->assertEquals(
            $verifiedAt->format('Y-m-d H:i:s'),
            $user->fresh()->email_verified_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_verify_user_requires_admin_session(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->postJson("/admin/users/{$user->id}/verify")
            ->assertRedirect('/admin/login');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_feature_diagram_returns_ok(): void
    {
        $diagram = Diagram::factory()->create();

        $this->asAdmin()
            ->postJson("/admin/diagrams/{$diagram->id}/feature", ['url' => 'https://example.com/img.png'])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_unfeature_diagram_returns_ok(): void
    {
        $diagram = Diagram::factory()->create(['featured' => true]);

        $this->asAdmin()
            ->deleteJson("/admin/diagrams/{$diagram->id}/feature")
            ->assertStatus(204);
    }

    public function test_logout_redirects(): void
    {
        $this->asAdmin()
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');
    }

    public function test_login_with_correct_credentials_sets_session_and_redirects(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => bcrypt('test-admin-secret')]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'test-admin-secret'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_with_wrong_credentials_returns_errors(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => bcrypt('correct-secret')]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-secret'])
            ->assertSessionHasErrors(['credentials']);
    }

    public function test_non_admin_cannot_login_to_admin(): void
    {
        $user = User::factory()->create(['role' => 'user', 'password' => bcrypt('Secret123')]);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Secret123'])
            ->assertSessionHasErrors(['credentials']);
    }

    public function test_admin_can_promote_and_demote_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->asAdmin()
            ->patchJson("/admin/users/{$user->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('role', 'admin');

        $this->assertSame('admin', $user->fresh()->role);

        $this->asAdmin()
            ->patchJson("/admin/users/{$user->id}/role", ['role' => 'user'])
            ->assertOk()
            ->assertJsonPath('role', 'user');

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_admin_cannot_demote_last_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patchJson("/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertStatus(422);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    private function asAdmin(): self
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']));
    }
}
