<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(['data' => [
            'id' => $user->id,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(['data' => $request->user()->tokens()
            ->latest()
            ->get()
            ->map(fn (PersonalAccessToken $token) => $this->tokenPayload($token))
            ->values()]);
    }

    public function destroyCurrent(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
        Auth::forgetGuards();

        return $this->noContent();
    }

    public function destroy(Request $request, PersonalAccessToken $token): JsonResponse
    {
        if ($token->tokenable_id !== $request->user()->id || $token->tokenable_type !== $request->user()->getMorphClass()) {
            abort(404);
        }

        $token->delete();
        Auth::forgetGuards();

        return $this->noContent();
    }

    /** @return array<string, mixed> */
    private function tokenPayload(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ];
    }
}
