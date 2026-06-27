<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores short-lived, server-side PKCE state for the delegated Foundry OAuth
 * flow. The code verifier never leaves the server.
 */
class FoundryOAuthStateService
{
    /**
     * @return array{state: string, code_challenge: string}
     */
    public function start(
        User $user,
        Diagram $diagram,
        string $hostUrl,
        string $clientId,
        ?string $desktopRedirectUri = null,
    ): array {
        $state = Str::random(64);
        $codeVerifier = Str::random(96);

        Cache::put($this->key($state), [
            'user_id' => $user->id,
            'diagram_id' => $diagram->id,
            'host_url' => $hostUrl,
            'client_id' => $clientId,
            'code_verifier' => $codeVerifier,
            'desktop_redirect_uri' => $desktopRedirectUri,
        ], now()->addSeconds($this->ttlSeconds()));

        return [
            'state' => $state,
            'code_challenge' => $this->challengeFor($codeVerifier),
        ];
    }

    /**
     * @return array{user_id: int, diagram_id: int, host_url: string, client_id: string, code_verifier: string, desktop_redirect_uri: ?string}
     *
     * @throws RuntimeException
     */
    public function consume(string $state): array
    {
        $record = Cache::pull($this->key($state));
        if (! is_array($record)) {
            throw new RuntimeException('The Foundry authorization request expired or was not found.');
        }

        return [
            'user_id' => (int) $record['user_id'],
            'diagram_id' => (int) $record['diagram_id'],
            'host_url' => (string) $record['host_url'],
            'client_id' => (string) $record['client_id'],
            'code_verifier' => (string) $record['code_verifier'],
            'desktop_redirect_uri' => isset($record['desktop_redirect_uri'])
                ? (string) $record['desktop_redirect_uri']
                : null,
        ];
    }

    public function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function key(string $state): string
    {
        return "foundry-oauth-state:{$state}";
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('foundry.oauth_state_ttl_seconds', 600));
    }
}
