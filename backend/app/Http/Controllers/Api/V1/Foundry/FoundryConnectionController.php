<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Foundry;

use App\Exceptions\FoundryException;
use App\Http\Controllers\Controller;
use App\Http\Resources\FoundryConnectionResource;
use App\Models\Diagram;
use App\Models\FoundryConnection;
use App\Services\Foundry\FoundryConnectionService;
use App\Services\Foundry\FoundryHostConfigService;
use App\Services\Foundry\FoundryPlatformService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Knuckles\Scribe\Attributes\Group;
use RuntimeException;

#[Group('Foundry')]
class FoundryConnectionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FoundryConnectionService $connections,
        private readonly FoundryHostConfigService $hostConfig,
        private readonly FoundryPlatformService $platform,
    ) {}

    /** Admin-configured hosts and whether custom hosts are permitted. */
    public function hosts(): JsonResponse
    {
        return $this->success([
            'data' => $this->hostConfig->listConfiguredHosts(),
            'allow_custom_hosts' => $this->hostConfig->allowsCustomHosts(),
        ]);
    }

    /** List the authenticated user's Foundry connections. */
    public function index(Request $request): JsonResponse
    {
        return $this->success([
            'data' => FoundryConnectionResource::collection(
                $this->connections->connectionsFor($request->user())
            )->resolve($request),
        ]);
    }

    /** Disconnect one of the authenticated user's Foundry connections. */
    public function destroy(Request $request, FoundryConnection $connection): JsonResponse
    {
        $this->connections->disconnect($request->user(), $connection);

        return $this->noContent();
    }

    /** Connection status for a diagram's Foundry host (current user). */
    public function status(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        if (! $diagram->isOntology()) {
            throw FoundryException::diagramNotOntology();
        }

        $host = $diagram->foundryConfig?->host_url;
        if (! is_string($host) || $host === '') {
            return $this->success(['data' => [
                'host_url' => null,
                'state' => 'host_not_set',
                'connected' => false,
                'configured' => false,
                'connectable' => false,
                'expires_at' => null,
                'display_name' => null,
                'auth_type' => null,
                'allow_token_auth' => (bool) config('foundry.allow_token_auth', true),
            ]]);
        }

        try {
            $status = $this->connections->status($request->user(), $host);
        } catch (FoundryException) {
            $status = [
                'host_url' => $host,
                'state' => 'host_not_configured',
                'connected' => false,
                'configured' => false,
                'connectable' => false,
                'expires_at' => null,
                'display_name' => null,
            ];
        }

        return $this->success(['data' => $status]);
    }

    /**
     * Begin delegated OAuth for the current user against the diagram's host.
     * Read access is sufficient: each user connects their own Foundry account.
     */
    public function connect(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $host = $this->platform->requireDiagramHost($diagram);

        $desktopRedirect = $this->sanitizeDesktopRedirect(
            is_string($request->input('redirect_uri')) ? $request->input('redirect_uri') : null
        );

        $result = $this->connections->beginAuthorization(
            $request->user(),
            $diagram,
            $host,
            $desktopRedirect,
        );

        return $this->success(['data' => $result]);
    }

    /**
     * Connect the diagram's host using a pasted Foundry token (personal/service
     * token). Works even when the host has no OAuth client configured. Read
     * access is sufficient: each user connects their own token.
     */
    public function connectWithToken(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $host = $this->platform->requireDiagramHost($diagram);

        $validated = $request->validate([
            'token' => ['required', 'string', 'min:8', 'max:8192'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (! (bool) config('foundry.allow_token_auth', true)) {
            throw FoundryException::tokenAuthDisabled();
        }

        $token = trim((string) $validated['token']);
        $expiresAt = isset($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null;

        // Verify the token by resolving the current Foundry user. Reject a
        // rejected token; tolerate transient runtime/upstream failures.
        $displayName = null;
        try {
            $displayName = $this->displayNameFromWhoami($this->platform->whoami($host, $token));
        } catch (FoundryException $exception) {
            if ($exception->errorCode === 'foundry_access_denied') {
                throw FoundryException::accessDenied('The Foundry token was rejected by the host.');
            }
            // Non-auth failure (runtime/network): store anyway; reads will surface issues.
        }

        $this->connections->connectWithToken($request->user(), $host, $token, $expiresAt, $displayName);

        return $this->success(['data' => $this->connections->status($request->user(), $host)]);
    }

    /**
     * OAuth callback hit by the browser following Foundry's redirect. This is
     * unauthenticated: the one-time server-side state identifies the user.
     */
    public function callback(Request $request): RedirectResponse|JsonResponse
    {
        $state = (string) $request->query('state', '');
        if ($state === '') {
            return $this->success(['error' => [
                'code' => 'foundry_upstream_unavailable',
                'message' => 'Missing OAuth state.',
            ]], 422);
        }

        try {
            $result = $this->connections->handleCallback(
                $state,
                is_string($request->query('code')) ? $request->query('code') : null,
                is_string($request->query('error')) ? $request->query('error') : null,
            );
        } catch (RuntimeException $exception) {
            return $this->success(['error' => [
                'code' => 'foundry_connection_required',
                'message' => $exception->getMessage(),
            ]], 422);
        }

        return redirect()->away($this->buildCallbackRedirect($result));
    }

    /**
     * @param array{status: string, message: ?string, diagram_id: int, host_url: string, desktop_redirect_uri: ?string} $result
     */
    private function buildCallbackRedirect(array $result): string
    {
        $query = array_filter([
            'foundry' => $result['status'],
            'host' => $result['host_url'],
            'message' => $result['message'],
        ], static fn ($value): bool => $value !== null && $value !== '');

        if (is_string($result['desktop_redirect_uri']) && $result['desktop_redirect_uri'] !== '') {
            return $this->appendQuery($result['desktop_redirect_uri'], $query);
        }

        $base = rtrim((string) config('app.url'), '/');
        $diagram = Diagram::find($result['diagram_id']);
        $target = $diagram
            ? $base.'/diagrams/'.$diagram->share_token
            : $base.'/diagrams';

        return $this->appendQuery($target, $query);
    }

    /** @param array<string, string> $query */
    private function appendQuery(string $uri, array $query): string
    {
        if ($query === []) {
            return $uri;
        }

        $separator = str_contains($uri, '?') ? '&' : '?';

        return $uri.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $me
     */
    private function displayNameFromWhoami(array $me): ?string
    {
        foreach (['username', 'email'] as $key) {
            if (is_string($me[$key] ?? null) && $me[$key] !== '') {
                return $me[$key];
            }
        }

        $given = is_string($me['givenName'] ?? null) ? $me['givenName'] : '';
        $family = is_string($me['familyName'] ?? null) ? $me['familyName'] : '';
        $full = trim($given.' '.$family);

        return $full !== '' ? $full : null;
    }

    private function sanitizeDesktopRedirect(?string $redirectUri): ?string
    {
        if ($redirectUri === null || $redirectUri === '') {
            return null;
        }

        if ($redirectUri === (string) config('services.desktop_oauth.redirect_uri')) {
            return $redirectUri;
        }

        // Allow native loopback redirects (desktop apps), e.g. http://127.0.0.1:PORT/...
        if (preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?(/.*)?$#', $redirectUri) === 1) {
            return $redirectUri;
        }

        return null;
    }
}
