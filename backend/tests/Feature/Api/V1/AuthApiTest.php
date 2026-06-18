<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\DesktopOAuthGrantService;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    public function test_desktop_authorize_validates_required_pkce_parameters(): void
    {
        $this->getJson('/api/v1/auth/oauth/google/authorize')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state', 'code_challenge', 'code_challenge_method', 'device_name', 'redirect_uri']);
    }

    public function test_desktop_oauth_callback_creates_one_time_grant_and_token_exchange_returns_expiring_bearer_token(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        config()->set('sanctum.expiration', 60);

        $verifier = str_repeat('a', 64);
        $state = str_repeat('s', 32);
        app(DesktopOAuthGrantService::class)->rememberRequest(
            $state,
            app(DesktopOAuthGrantService::class)->challengeForVerifier($verifier),
            'ontolosql://oauth/callback',
            'Dioxus Desktop',
        );
        $this->mockGoogleUser('google-desktop-1', 'desktop@company.com', 'company.com');

        $callback = $this->get('/api/v1/auth/oauth/google/callback?state='.$state)
            ->assertRedirect();
        parse_str((string) parse_url($callback->headers->get('Location'), PHP_URL_QUERY), $query);

        $response = $this->postJson('/api/v1/auth/oauth/google/token', [
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'desktop@company.com')
            ->assertJsonStructure(['access_token', 'expires_at']);

        $token = $response->json('access_token');
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'desktop@company.com');

        $this->postJson('/api/v1/auth/oauth/google/token', [
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertUnprocessable();
    }

    public function test_desktop_oauth_callback_rejects_wrong_google_domain(): void
    {
        config()->set('services.google.allowed_domain', 'company.com');
        $state = str_repeat('s', 32);
        app(DesktopOAuthGrantService::class)->rememberRequest(
            $state,
            app(DesktopOAuthGrantService::class)->challengeForVerifier(str_repeat('a', 64)),
            'ontolosql://oauth/callback',
            'Dioxus Desktop',
        );
        $this->mockGoogleUser('google-desktop-2', 'desktop@other.com', 'company.com');

        $this->get('/api/v1/auth/oauth/google/callback?state='.$state)
            ->assertForbidden()
            ->assertJsonPath('message', 'Google account is not allowed.');

        $this->assertDatabaseMissing('users', ['google_id' => 'google-desktop-2']);
    }

    public function test_token_listing_does_not_expose_plaintext_token_and_revoke_invalidates_token(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('Dioxus Desktop', ['desktop', 'tokens:manage'], now()->addHour());

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Dioxus Desktop')
            ->assertJsonMissing(['access_token' => $token->plainTextToken]);

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/v1/auth/token/current')
            ->assertNoContent();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_web_logout_does_not_revoke_existing_desktop_tokens(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('Secret1!'),
        ]);
        $token = $user->createToken('Dioxus Desktop', ['desktop'], now()->addHour());

        $this->withHeader('referer', config('app.url'))
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret1!'])
            ->assertOk();
        $this->withHeader('referer', config('app.url'))
            ->postJson('/api/logout')
            ->assertOk();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    private function mockGoogleUser(string $id, string $email, ?string $hostedDomain): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->once()->andReturnSelf();
        $provider->shouldReceive('redirectUrl')->once()->with((string) config('services.google.desktop_redirect'))->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn(
            (new SocialiteUser())
                ->setRaw(array_filter(['hd' => $hostedDomain]))
                ->map(['id' => $id, 'email' => $email])
        );

        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }
}
