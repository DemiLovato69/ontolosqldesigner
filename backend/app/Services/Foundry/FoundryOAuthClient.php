<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to a Foundry stack's OAuth2 endpoints (Authorization Code + PKCE).
 *
 * Foundry exposes these under /multipass/api/oauth2. All network access is via
 * the Http facade so it can be faked in tests.
 */
class FoundryOAuthClient
{
    private const AUTHORIZE_PATH = '/multipass/api/oauth2/authorize';

    private const TOKEN_PATH = '/multipass/api/oauth2/token';

    /**
     * @param list<string> $scopes
     */
    public function buildAuthorizeUrl(
        string $hostUrl,
        string $clientId,
        string $codeChallenge,
        string $state,
        array $scopes,
        string $redirectUri,
    ): string {
        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'scope' => implode(' ', $scopes),
        ], '', '&', PHP_QUERY_RFC3986);

        return rtrim($hostUrl, '/').self::AUTHORIZE_PATH.'?'.$query;
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string, token_type: ?string}
     *
     * @throws FoundryException
     */
    public function exchangeCode(
        string $hostUrl,
        string $clientId,
        ?string $clientSecret,
        string $code,
        string $codeVerifier,
        string $redirectUri,
    ): array {
        return $this->requestToken($hostUrl, array_filter([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code_verifier' => $codeVerifier,
        ], static fn ($value): bool => $value !== null));
    }

    /**
     * Refresh an access token.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string, token_type: ?string}
     *
     * @throws FoundryException
     */
    public function refresh(
        string $hostUrl,
        string $clientId,
        ?string $clientSecret,
        string $refreshToken,
    ): array {
        return $this->requestToken($hostUrl, array_filter([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ], static fn ($value): bool => $value !== null));
    }

    /**
     * @param array<string, string> $payload
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string, token_type: ?string}
     *
     * @throws FoundryException
     */
    private function requestToken(string $hostUrl, array $payload): array
    {
        $url = rtrim($hostUrl, '/').self::TOKEN_PATH;

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(20)
                ->post($url, $payload);
        } catch (ConnectionException) {
            throw FoundryException::upstreamUnavailable('Could not reach the Foundry host for authentication.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw FoundryException::accessDenied('Foundry rejected the authentication request.');
        }

        if ($response->failed()) {
            throw FoundryException::upstreamUnavailable('Foundry token exchange failed.');
        }

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw FoundryException::upstreamUnavailable('Foundry returned an invalid token response.');
        }

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            'expires_in' => is_numeric($data['expires_in'] ?? null) ? (int) $data['expires_in'] : null,
            'scope' => is_string($data['scope'] ?? null) ? $data['scope'] : null,
            'token_type' => is_string($data['token_type'] ?? null) ? $data['token_type'] : null,
        ];
    }
}
