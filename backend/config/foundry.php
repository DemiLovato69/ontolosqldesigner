<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Hosts
    |--------------------------------------------------------------------------
    |
    | When false (recommended for production), users may only connect to and
    | query Foundry hosts that are explicitly configured in the "hosts" map
    | below. Diagram owners can still save any host on a diagram, but a
    | connection/query against an unconfigured host returns a
    | "foundry_host_not_configured" error.
    |
    | When true, owner-entered hosts that are not in the map are permitted
    | using the "custom_host" OAuth client credentials defined below.
    |
    */

    'allow_custom_hosts' => (bool) env('FOUNDRY_ALLOW_CUSTOM_HOSTS', false),

    /*
    |--------------------------------------------------------------------------
    | Token Authentication
    |--------------------------------------------------------------------------
    |
    | Allow users to connect a host by pasting a Foundry token (personal or
    | service token) instead of completing the OAuth flow. This works even for
    | hosts that have no OAuth client configured. Set to false to enforce
    | OAuth-only connections.
    |
    */

    'allow_token_auth' => (bool) env('FOUNDRY_ALLOW_TOKEN_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Host Safety
    |--------------------------------------------------------------------------
    |
    | Foundry hosts must use HTTPS and resolve to public hosts. Loopback and
    | private hosts are rejected unless explicitly allowed (useful for local
    | development against a tunnel or mock).
    |
    */

    'allow_insecure_hosts' => (bool) env('FOUNDRY_ALLOW_INSECURE_HOSTS', false),

    /*
    |--------------------------------------------------------------------------
    | OAuth
    |--------------------------------------------------------------------------
    |
    | Read-only delegated OAuth uses the Authorization Code flow with PKCE.
    | The redirect URI must be registered on every Foundry OAuth client that
    | users will connect through.
    |
    */

    'redirect_uri' => env('FOUNDRY_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/api/v1/foundry/oauth/callback'),

    'default_scopes' => array_values(array_filter(array_map(
        'trim',
        explode(' ', (string) env('FOUNDRY_DEFAULT_SCOPES', 'api:read-data offline_access'))
    ))),

    'oauth_state_ttl_seconds' => max(60, (int) env('FOUNDRY_OAUTH_STATE_TTL_SECONDS', 600)),

    /*
    |--------------------------------------------------------------------------
    | Custom Host OAuth Client
    |--------------------------------------------------------------------------
    |
    | Used only when "allow_custom_hosts" is true and the requested host is not
    | present in the configured "hosts" map.
    |
    */

    'custom_host' => [
        'client_id' => env('FOUNDRY_CUSTOM_HOST_CLIENT_ID'),
        'client_secret' => env('FOUNDRY_CUSTOM_HOST_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configured Hosts
    |--------------------------------------------------------------------------
    |
    | Admin-approved Foundry hosts keyed by their normalized base URL (scheme +
    | host [+ optional port], no trailing slash). Provide via FOUNDRY_HOSTS_JSON
    | as a JSON object, for example:
    |
    | {
    |   "https://example.palantirfoundry.com": {
    |     "client_id": "abc123",
    |     "client_secret": null,
    |     "display_name": "Example Foundry"
    |   }
    | }
    |
    | A null/absent client_secret means the host uses a public PKCE client.
    |
    | Hosts can also be managed from the admin dashboard (/admin/foundry), which
    | stores them in the `foundry_host_configs` table. Enabled DB hosts take
    | precedence over entries in this env map.
    |
    */

    'hosts' => is_array($decodedFoundryHosts = json_decode((string) env('FOUNDRY_HOSTS_JSON', '{}'), true))
        ? $decodedFoundryHosts
        : [],

    /*
    |--------------------------------------------------------------------------
    | Node Runtime Bridge
    |--------------------------------------------------------------------------
    |
    | The Foundry Platform SDK (@osdk/foundry) runs in a small Node bridge that
    | Laravel invokes per operation. Access tokens are passed over stdin and are
    | never written to argv or logs.
    |
    */

    'runtime' => [
        'node' => env('FOUNDRY_RUNTIME_NODE', 'node'),
        'script' => env(
            'FOUNDRY_RUNTIME_SCRIPT',
            is_file(base_path('../foundry-runtime/foundry.mjs'))
                ? base_path('../foundry-runtime/foundry.mjs')
                : '/opt/ontolosql-foundry/foundry.mjs'
        ),
        'timeout' => max(5, (int) env('FOUNDRY_RUNTIME_TIMEOUT', 30)),
    ],

];
