<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Laravel\Socialite\Two\User as SocialiteUser;

class GoogleOAuthService
{
    /**
     * @throws AuthenticationException
     */
    public function findOrCreateAllowedUser(SocialiteUser $googleUser): User
    {
        $allowedDomain = strtolower((string) config('services.google.allowed_domain'));
        $email = strtolower((string) $googleUser->getEmail());
        $emailDomain = substr(strrchr($email, '@') ?: '', 1);

        if (
            $allowedDomain === ''
            || strtolower((string) ($googleUser->getRaw()['hd'] ?? '')) !== $allowedDomain
            || $emailDomain !== $allowedDomain
        ) {
            throw new AuthenticationException('Google account is not in the allowed company domain.');
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first()
            ?? new User(['email' => $email]);

        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
