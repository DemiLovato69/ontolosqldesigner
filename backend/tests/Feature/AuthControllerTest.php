<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
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

    public function test_login_returns_user_and_authenticates_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret1!')]);

        $this->fromFrontend()->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret1!'])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => true])
            ->assertJsonPath('user.email', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_succeeds(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Secret1!')]);

        $this->fromFrontend()->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret1!'])
            ->assertOk();

        $this->fromFrontend()
            ->postJson('/api/logout')
            ->assertStatus(200);

        $this->fromFrontend()
            ->getJson('/api/user')
            ->assertUnauthorized();
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

        $this->fromFrontend()->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-pass'])
            ->assertStatus(401)
            ->assertJsonFragment(['status' => false]);
    }

    public function test_google_callback_creates_allowed_domain_user(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $this->mockGoogleUser('google-1', 'new@company.com', 'company.com');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertSame('http://localhost:8080/oauth/callback', $response->headers->get('Location'));

        $this->assertDatabaseHas('users', [
            'email' => 'new@company.com',
            'google_id' => 'google-1',
        ]);
        $this->assertNotNull(User::where('email', 'new@company.com')->first()->email_verified_at);
    }

    public function test_google_callback_links_existing_email_user(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $user = User::factory()->create(['email' => 'existing@company.com', 'google_id' => null]);
        $this->mockGoogleUser('google-2', 'existing@company.com', 'company.com');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertSame('http://localhost:8080/oauth/callback', $response->headers->get('Location'));

        $this->assertSame('google-2', $user->refresh()->google_id);
    }

    public function test_google_callback_logs_in_existing_google_user(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $user = User::factory()->create(['email' => 'user@company.com', 'google_id' => 'google-3']);
        $this->mockGoogleUser('google-3', 'user@company.com', 'company.com');

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect();
        $this->assertSame('http://localhost:8080/oauth/callback', $response->headers->get('Location'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_google_callback_rejects_wrong_hosted_domain(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $this->mockGoogleUser('google-4', 'user@company.com', 'other.com');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login?oauth_error=company_domain');

        $this->assertDatabaseMissing('users', ['google_id' => 'google-4']);
    }

    public function test_google_callback_rejects_wrong_email_domain(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $this->mockGoogleUser('google-5', 'user@other.com', 'company.com');

        $this->get('/auth/google/callback')
            ->assertRedirect('/login?oauth_error=company_domain');

        $this->assertDatabaseMissing('users', ['google_id' => 'google-5']);
    }

    public function test_non_google_oauth_routes_are_not_available(): void
    {
        $this->get('/auth/github')->assertNotFound();
        $this->get('/auth/gitlab')->assertNotFound();
    }

    public function test_admin_google_entry_flags_intent_and_redirects_to_google(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/admin/auth/google')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
            ->assertSessionHas('admin_oauth_intended', true);
    }

    public function test_google_callback_with_admin_intent_redirects_admin_to_dashboard(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $admin = User::factory()->create(['email' => 'boss@company.com', 'google_id' => 'google-admin', 'role' => 'admin']);
        $this->mockGoogleUser('google-admin', 'boss@company.com', 'company.com');

        $this->withSession(['admin_oauth_intended' => true])
            ->get('/auth/google/callback')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_google_callback_with_admin_intent_rejects_non_admin(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        User::factory()->create(['email' => 'user@company.com', 'google_id' => 'google-plain', 'role' => 'user']);
        $this->mockGoogleUser('google-plain', 'user@company.com', 'company.com');

        $this->withSession(['admin_oauth_intended' => true])
            ->get('/auth/google/callback')
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['credentials']);

        $this->assertGuest();
    }

    private function mockGoogleUser(string $id, string $email, ?string $hostedDomain): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialiteUser())
                ->setRaw(array_filter(['hd' => $hostedDomain]))
                ->map(['id' => $id, 'email' => $email])
        );

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    private function fromFrontend(): self
    {
        return $this->withHeader('referer', config('app.url'));
    }
}
