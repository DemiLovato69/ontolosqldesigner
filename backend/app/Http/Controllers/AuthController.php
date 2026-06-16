<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Knuckles\Scribe\Attributes\Group;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

#[Group('Authentication')]
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $token = $this->authService->login($request->input('email'), $request->input('password'));
        } catch (AuthenticationException) {
            return $this->success(['status' => false, 'message' => 'Wrong email or password'], 401);
        }

        return $this->success(['status' => true, 'token' => $token, 'message' => 'Logged in successfully']);
    }

    public function redirectToGoogle(): RedirectResponse|SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable) {
            return $this->oauthError();
        }

        $allowedDomain = strtolower((string) config('services.google.allowed_domain'));
        $email = strtolower((string) $googleUser->getEmail());
        $emailDomain = substr(strrchr($email, '@') ?: '', 1);

        if (
            $allowedDomain === ''
            || strtolower((string) ($googleUser->getRaw()['hd'] ?? '')) !== $allowedDomain
            || $emailDomain !== $allowedDomain
        ) {
            return $this->oauthError();
        }

        $user = $this->findOrCreateGoogleUser($googleUser, $email);
        Auth::login($user);

        $token = $user->createToken('API TOKEN')->plainTextToken;

        return redirect('/oauth/callback?'.http_build_query([
            'token' => $token,
            'avatar' => $googleUser->getAvatar(),
        ]));
    }

    private function findOrCreateGoogleUser(SocialiteUser $googleUser, string $email): User
    {
        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first()
            ?? new User(['email' => $email]);

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function oauthError(): RedirectResponse
    {
        return redirect('/login?oauth_error=company_domain');
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->success([
            'status' => $this->authService->logout($request->user()),
            'message' => 'Logged out successfully',
        ]);
    }

    public function verifyEmail(string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        if (! $this->authService->verifyEmail($user, $hash)) {
            abort(403);
        }

        return redirect('/diagrams?verified=1');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $sent = $this->authService->resendVerification($request->user());

        return $this->success([
            'message' => $sent ? 'Verification email sent' : 'Email already verified',
        ]);
    }
}
