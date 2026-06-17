<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class DesktopOAuthGrantService
{
    public function rememberRequest(string $state, string $codeChallenge, string $redirectUri, string $deviceName): void
    {
        $this->validateRedirectUri($redirectUri);

        Cache::put($this->requestKey($state), [
            'code_challenge' => $codeChallenge,
            'redirect_uri' => $redirectUri,
            'device_name' => $deviceName,
        ], now()->addSeconds($this->ttlSeconds()));
    }

    /** @return array{redirect_uri: string, state: string} */
    public function createGrant(string $state, User $user): array
    {
        $request = Cache::pull($this->requestKey($state));
        if (! is_array($request)) {
            throw new RuntimeException('OAuth request expired or was not found.');
        }

        $code = Str::random(80);
        Cache::put($this->grantKey($code), [
            'user_id' => $user->id,
            'code_challenge' => $request['code_challenge'],
            'device_name' => $request['device_name'],
        ], now()->addSeconds($this->ttlSeconds()));

        return [
            'redirect_uri' => $this->appendQuery((string) $request['redirect_uri'], [
                'code' => $code,
                'state' => $state,
            ]),
            'state' => $state,
        ];
    }

    /** @return array{user: User, device_name: string} */
    public function consumeGrant(string $code, string $codeVerifier): array
    {
        $grant = Cache::pull($this->grantKey($code));
        if (! is_array($grant)) {
            throw new RuntimeException('OAuth code expired or was already used.');
        }

        if (! hash_equals((string) $grant['code_challenge'], $this->challengeForVerifier($codeVerifier))) {
            throw new RuntimeException('OAuth code verifier is invalid.');
        }

        $user = User::find($grant['user_id']);
        if (! $user) {
            throw new RuntimeException('OAuth user no longer exists.');
        }

        return [
            'user' => $user,
            'device_name' => (string) $grant['device_name'],
        ];
    }

    public function challengeForVerifier(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function validateRedirectUri(string $redirectUri): void
    {
        if ($redirectUri !== (string) config('services.desktop_oauth.redirect_uri')) {
            throw new RuntimeException('Desktop redirect URI is not allowed.');
        }
    }

    /** @param array<string, string> $query */
    private function appendQuery(string $uri, array $query): string
    {
        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function requestKey(string $state): string
    {
        return "desktop-oauth-request:{$state}";
    }

    private function grantKey(string $code): string
    {
        return "desktop-oauth-grant:{$code}";
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('services.desktop_oauth.grant_ttl_seconds', 300));
    }
}
