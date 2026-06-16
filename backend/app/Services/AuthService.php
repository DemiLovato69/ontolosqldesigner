<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendVerificationEmail;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use SensitiveParameter;

class AuthService
{
    /**
     * @throws AuthenticationException If the credentials are invalid.
     */
    public function login(string $email, #[SensitiveParameter] string $password): User
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw new AuthenticationException('Invalid credentials.');
        }

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function verifyEmail(User $user, string $hash): bool
    {
        if (! hash_equals(sha1($user->email), $hash)) {
            return false;
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return true;
    }

    /**
     * @return bool True if the email was sent; false if already verified.
     */
    public function resendVerification(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        SendVerificationEmail::dispatch($user);

        return true;
    }

    public function logout(User $user): true
    {
        $user->tokens()->delete();
        Auth::guard('web')->logout();

        return true;
    }
}
