<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\GoogleOAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Knuckles\Scribe\Attributes\Group;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

#[Group('Authentication')]
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly GoogleOAuthService $googleOAuthService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->login($request->input('email'), $request->input('password'));
            $request->session()->regenerate();
        } catch (AuthenticationException) {
            return $this->success(['status' => false, 'message' => 'Wrong email or password'], 401);
        }

        return $this->success(['status' => true, 'user' => $user, 'message' => 'Logged in successfully']);
    }

    public function redirectToGoogle(): RedirectResponse|SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $adminIntended = (bool) $request->session()->pull('admin_oauth_intended', false);

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Throwable) {
            return $this->oauthError($adminIntended);
        }

        try {
            $user = $this->googleOAuthService->findOrCreateAllowedUser($googleUser);
        } catch (AuthenticationException) {
            return $this->oauthError($adminIntended);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($adminIntended) {
            if ($user->isAdmin()) {
                return redirect('/admin');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'credentials' => 'That Google account is not an admin.',
            ]);
        }

        return redirect('/oauth/callback');
    }

    private function oauthError(bool $adminIntended = false): RedirectResponse
    {
        if ($adminIntended) {
            return redirect()->route('admin.login')->withErrors([
                'credentials' => 'Google sign-in failed or that account is not allowed.',
            ]);
        }

        return redirect('/login?oauth_error=company_domain');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();

        return $this->success([
            'status' => true,
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
