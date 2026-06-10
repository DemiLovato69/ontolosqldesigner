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
use Knuckles\Scribe\Attributes\Group;

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
