<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use App\Models\Diagram;
use App\Models\FoundryConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Coordinates the delegated, per-user Foundry OAuth lifecycle and token access.
 *
 * Tokens are stored per (user, host) so collaborators never reuse the diagram
 * owner's Foundry access.
 */
class FoundryConnectionService
{
    public function __construct(
        private readonly FoundryHostConfigService $hosts,
        private readonly FoundryOAuthStateService $state,
        private readonly FoundryOAuthClient $oauth,
    ) {}

    /**
     * Begin authorization for a diagram's host.
     *
     * @return array{authorize_url: string, state: string, host_url: string}
     *
     * @throws FoundryException
     */
    public function beginAuthorization(
        User $user,
        Diagram $diagram,
        string $hostUrl,
        ?string $desktopRedirectUri = null,
    ): array {
        $client = $this->hosts->resolveClient($hostUrl);

        $state = $this->state->start(
            $user,
            $diagram,
            $client['host_url'],
            $client['client_id'],
            $desktopRedirectUri,
        );

        $authorizeUrl = $this->oauth->buildAuthorizeUrl(
            $client['host_url'],
            $client['client_id'],
            $state['code_challenge'],
            $state['state'],
            $this->scopes(),
            $this->redirectUri(),
        );

        return [
            'authorize_url' => $authorizeUrl,
            'state' => $state['state'],
            'host_url' => $client['host_url'],
        ];
    }

    /**
     * Complete authorization from the OAuth callback. Consumes the one-time
     * state atomically and returns a structured result (including redirect
     * targets) for both the success and error paths.
     *
     * @return array{status: string, message: ?string, connection: ?FoundryConnection, diagram_id: int, host_url: string, desktop_redirect_uri: ?string}
     *
     * @throws \RuntimeException when the state is missing/expired
     */
    public function handleCallback(string $state, ?string $code, ?string $error = null): array
    {
        $record = $this->state->consume($state);

        $result = [
            'status' => 'error',
            'message' => null,
            'connection' => null,
            'diagram_id' => $record['diagram_id'],
            'host_url' => $record['host_url'],
            'desktop_redirect_uri' => $record['desktop_redirect_uri'],
        ];

        if ($error !== null && $error !== '') {
            $result['message'] = 'Foundry authorization was denied or cancelled.';

            return $result;
        }

        if ($code === null || $code === '') {
            $result['message'] = 'Foundry did not return an authorization code.';

            return $result;
        }

        try {
            $client = $this->hosts->resolveClient($record['host_url']);

            $tokens = $this->oauth->exchangeCode(
                $client['host_url'],
                $client['client_id'],
                $client['client_secret'],
                $code,
                $record['code_verifier'],
                $this->redirectUri(),
            );

            $connection = $this->storeTokens(
                $record['user_id'],
                $client['host_url'],
                $client['client_id'],
                $client['display_name'],
                $tokens,
            );
        } catch (FoundryException $exception) {
            $result['message'] = $exception->getMessage();

            return $result;
        }

        $result['status'] = 'connected';
        $result['connection'] = $connection;

        return $result;
    }

    /**
     * Connect a host using a Foundry token (personal/service token) instead of
     * OAuth. Works for hosts without an OAuth client configured.
     *
     * @throws FoundryException
     */
    public function connectWithToken(
        User $user,
        string $hostUrl,
        string $token,
        ?Carbon $expiresAt = null,
        ?string $displayName = null,
    ): FoundryConnection {
        if (! (bool) config('foundry.allow_token_auth', true)) {
            throw FoundryException::tokenAuthDisabled();
        }

        $normalized = $this->hosts->normalize($hostUrl);

        return FoundryConnection::updateOrCreate(
            ['user_id' => $user->id, 'host_url' => $normalized],
            [
                'auth_type' => FoundryConnection::AUTH_TOKEN,
                'client_id' => null,
                'display_name' => $displayName,
                'scopes' => [],
                'access_token' => $token,
                'refresh_token' => null,
                'expires_at' => $expiresAt,
                'last_used_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    /**
     * Return a valid access token for the user on the host, refreshing if needed.
     *
     * @throws FoundryException
     */
    public function freshAccessToken(User $user, string $hostUrl): string
    {
        $normalized = $this->hosts->normalize($hostUrl);
        $connection = $this->connectionFor($user, $normalized);

        if (! $connection || $connection->isRevoked() || $connection->access_token === null) {
            throw FoundryException::connectionRequired();
        }

        if (! $connection->isExpired()) {
            $connection->forceFill(['last_used_at' => now()])->save();

            return (string) $connection->access_token;
        }

        if (! $connection->canRefresh()) {
            throw FoundryException::connectionExpired();
        }

        $client = $this->hosts->resolveClient($normalized);

        try {
            $tokens = $this->oauth->refresh(
                $client['host_url'],
                $client['client_id'],
                $client['client_secret'],
                (string) $connection->refresh_token,
            );
        } catch (FoundryException $exception) {
            if ($exception->errorCode === 'foundry_access_denied') {
                throw FoundryException::connectionExpired();
            }

            throw $exception;
        }

        $connection = $this->storeTokens(
            $user->id,
            $client['host_url'],
            $client['client_id'],
            $connection->display_name ?? $client['display_name'],
            $tokens,
        );

        return (string) $connection->access_token;
    }

    /**
     * Build a UI-friendly connection status for a host without throwing for the
     * common "needs setup" / "needs connect" states.
     *
     * @return array{host_url: string, configured: bool, connectable: bool, connected: bool, state: string, expires_at: ?string, display_name: ?string, auth_type: ?string, allow_token_auth: bool}
     */
    public function status(User $user, string $hostUrl): array
    {
        $normalized = $this->hosts->normalize($hostUrl);

        $configured = false;
        $connectable = false;
        try {
            $client = $this->hosts->resolveClient($normalized);
            $configured = $client['configured'];
            $connectable = true;
        } catch (FoundryException) {
            // Host is saved on the diagram but cannot be connected yet.
        }

        $connection = $this->connectionFor($user, $normalized);

        // An active (or OAuth-refreshable) connection wins, even when the host
        // has no OAuth client configured (e.g. token-based connections).
        if ($connection && $connection->isActive()) {
            $state = 'connected';
        } elseif ($connection && ! $connection->isRevoked() && $connection->canRefresh()) {
            $state = 'connected';
        } elseif ($connection && $connection->isExpired()) {
            $state = 'expired';
        } elseif (! $connectable) {
            $state = 'host_not_configured';
        } else {
            $state = 'disconnected';
        }

        return [
            'host_url' => $normalized,
            'configured' => $configured,
            'connectable' => $connectable,
            'connected' => $state === 'connected',
            'state' => $state,
            'expires_at' => $connection?->expires_at?->toIso8601String(),
            'display_name' => $connection?->display_name,
            'auth_type' => $connection?->auth_type,
            'allow_token_auth' => (bool) config('foundry.allow_token_auth', true),
        ];
    }

    public function disconnect(User $user, FoundryConnection $connection): void
    {
        if ($connection->user_id !== $user->id) {
            throw FoundryException::accessDenied('You can only disconnect your own Foundry connections.');
        }

        $connection->delete();
    }

    /** @return Collection<int, FoundryConnection> */
    public function connectionsFor(User $user): Collection
    {
        return $user->foundryConnections()->orderBy('host_url')->get();
    }

    public function connectionFor(User $user, string $hostUrl): ?FoundryConnection
    {
        return FoundryConnection::query()
            ->where('user_id', $user->id)
            ->where('host_url', $hostUrl)
            ->first();
    }

    /**
     * @param array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string, token_type: ?string} $tokens
     */
    private function storeTokens(
        int $userId,
        string $hostUrl,
        string $clientId,
        ?string $displayName,
        array $tokens,
    ): FoundryConnection {
        $scopes = $tokens['scope'] !== null && $tokens['scope'] !== ''
            ? array_values(array_filter(explode(' ', $tokens['scope'])))
            : $this->scopes();

        return FoundryConnection::updateOrCreate(
            ['user_id' => $userId, 'host_url' => $hostUrl],
            [
                'auth_type' => FoundryConnection::AUTH_OAUTH,
                'client_id' => $clientId,
                'display_name' => $displayName,
                'scopes' => $scopes,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => $tokens['expires_in'] !== null ? now()->addSeconds($tokens['expires_in']) : null,
                'last_used_at' => now(),
                'revoked_at' => null,
            ],
        );
    }

    /** @return list<string> */
    private function scopes(): array
    {
        $scopes = config('foundry.default_scopes', []);

        return is_array($scopes) ? array_values(array_map('strval', $scopes)) : [];
    }

    private function redirectUri(): string
    {
        return (string) config('foundry.redirect_uri');
    }
}
