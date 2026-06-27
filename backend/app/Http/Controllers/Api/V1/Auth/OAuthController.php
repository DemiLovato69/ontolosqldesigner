<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\DesktopOAuthGrantService;
use App\Services\GoogleOAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class OAuthController extends Controller
{
    private const ABILITIES = [
        'desktop',
        'diagrams:read',
        'diagrams:write',
        'diagrams:delete',
        'imports:write',
        'exports:read',
        'sharing:write',
        'changelog:read',
        'changelog:write',
        'presence:read',
        'presence:write',
        'foundry:connect',
        'foundry:read',
        'foundry:llm',
        'tokens:manage',
    ];

    public function __construct(
        private readonly DesktopOAuthGrantService $grants,
        private readonly GoogleOAuthService $googleOAuth,
    ) {}

    public function authorize(Request $request): RedirectResponse|SymfonyRedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'string', 'min:16', 'max:255'],
            'code_challenge' => ['required', 'string', 'min:43', 'max:128'],
            'code_challenge_method' => ['required', 'in:S256'],
            'device_name' => ['required', 'string', 'max:120'],
            'redirect_uri' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->grants->rememberRequest(
                $validated['state'],
                $validated['code_challenge'],
                $validated['redirect_uri'],
                $validated['device_name'],
            );
        } catch (RuntimeException $exception) {
            return $this->success(['message' => $exception->getMessage()], 422);
        }

        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl((string) config('services.google.desktop_redirect'))
            ->with(['state' => $validated['state']])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        $state = (string) $request->query('state', '');
        if ($state === '') {
            return $this->success(['message' => 'OAuth state is required.'], 422);
        }

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl((string) config('services.google.desktop_redirect'))
                ->user();
            $user = $this->googleOAuth->findOrCreateAllowedUser($googleUser);
            $grant = $this->grants->createGrant($state, $user);
        } catch (AuthenticationException) {
            return $this->success(['message' => 'Google account is not allowed.'], 403);
        } catch (RuntimeException $exception) {
            return $this->success(['message' => $exception->getMessage()], 422);
        } catch (Throwable) {
            return $this->success(['message' => 'Google OAuth failed.'], 422);
        }

        return redirect()->away($grant['redirect_uri']);
    }

    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $grant = $this->grants->consumeGrant($validated['code'], $validated['code_verifier']);
        } catch (RuntimeException $exception) {
            return $this->success(['message' => $exception->getMessage()], 422);
        }

        $expiresAt = $this->tokenExpiration();
        $token = $grant['user']->createToken(
            $validated['device_name'] ?? $grant['device_name'],
            self::ABILITIES,
            $expiresAt,
        );

        return $this->success([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt?->toIso8601String(),
            'user' => $this->userPayload($grant['user']),
        ]);
    }

    /** @return array<string, mixed> */
    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    private function tokenExpiration(): ?Carbon
    {
        $minutes = config('sanctum.expiration');

        return $minutes ? now()->addMinutes((int) $minutes) : null;
    }
}
