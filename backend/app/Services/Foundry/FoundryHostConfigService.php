<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;
use App\Models\FoundryHostConfig;

/**
 * Resolves and validates Foundry hosts under the hybrid model: diagram owners
 * may enter any host, but connecting/querying requires either an admin-
 * configured host or an explicitly enabled custom-host OAuth client.
 */
class FoundryHostConfigService
{
    /**
     * Normalize a host into "scheme://host[:port]" with no path/query/fragment.
     * Accepts a bare domain and assumes https.
     *
     * @throws FoundryException
     */
    public function normalize(string $hostUrl): string
    {
        $candidate = trim($hostUrl);
        if ($candidate === '') {
            throw FoundryException::hostNotAllowed('A Foundry host URL is required.');
        }

        if (! preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $candidate)) {
            $candidate = 'https://'.$candidate;
        }

        $parts = parse_url($candidate);
        if ($parts === false || empty($parts['host'])) {
            throw FoundryException::hostNotAllowed('The Foundry host URL is invalid.');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);

        if ($scheme !== 'https' && ! $this->allowsInsecureHosts()) {
            throw FoundryException::hostNotAllowed('The Foundry host must use HTTPS.');
        }

        if ($scheme !== 'https' && $scheme !== 'http') {
            throw FoundryException::hostNotAllowed('The Foundry host must use HTTPS.');
        }

        $this->guardHost($host);

        $normalized = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        return $normalized;
    }

    public function allowsCustomHosts(): bool
    {
        return (bool) config('foundry.allow_custom_hosts', false);
    }

    public function allowsInsecureHosts(): bool
    {
        return (bool) config('foundry.allow_insecure_hosts', false);
    }

    /**
     * Resolve the OAuth client config to use for a host.
     *
     * @return array{host_url: string, client_id: string, client_secret: ?string, display_name: string, configured: bool}
     *
     * @throws FoundryException
     */
    public function resolveClient(string $hostUrl): array
    {
        $normalized = $this->normalize($hostUrl);

        // Admin-managed DB hosts take precedence over the env map.
        $dbHost = $this->dbHost($normalized);
        if ($dbHost !== null) {
            if ((string) $dbHost->client_id === '') {
                throw FoundryException::hostNotConfigured();
            }

            return [
                'host_url' => $normalized,
                'client_id' => (string) $dbHost->client_id,
                'client_secret' => $this->stringOrNull($dbHost->client_secret),
                'display_name' => (string) ($dbHost->display_name ?: $normalized),
                'configured' => true,
            ];
        }

        $configured = $this->configuredHost($normalized);
        if ($configured !== null) {
            $clientId = (string) ($configured['client_id'] ?? '');
            if ($clientId === '') {
                throw FoundryException::hostNotConfigured();
            }

            return [
                'host_url' => $normalized,
                'client_id' => $clientId,
                'client_secret' => $this->stringOrNull($configured['client_secret'] ?? null),
                'display_name' => (string) ($configured['display_name'] ?? $normalized),
                'configured' => true,
            ];
        }

        if (! $this->allowsCustomHosts()) {
            throw FoundryException::hostNotConfigured();
        }

        $clientId = (string) config('foundry.custom_host.client_id', '');
        if ($clientId === '') {
            throw FoundryException::hostNotConfigured();
        }

        return [
            'host_url' => $normalized,
            'client_id' => $clientId,
            'client_secret' => $this->stringOrNull(config('foundry.custom_host.client_secret')),
            'display_name' => $normalized,
            'configured' => false,
        ];
    }

    /**
     * Whether a host can be connected to/queried (configured or custom allowed).
     */
    public function isConnectable(string $hostUrl): bool
    {
        try {
            $this->resolveClient($hostUrl);

            return true;
        } catch (FoundryException) {
            return false;
        }
    }

    /**
     * Admin-configured hosts for discovery, without secrets.
     *
     * @return list<array{host_url: string, display_name: string}>
     */
    public function listConfiguredHosts(): array
    {
        $hosts = [];

        // Admin-managed DB hosts (enabled) take precedence over env entries.
        foreach (FoundryHostConfig::query()->where('enabled', true)->get() as $dbHost) {
            try {
                $normalized = $this->normalize((string) $dbHost->host_url);
            } catch (FoundryException) {
                continue;
            }
            $hosts[$normalized] = [
                'host_url' => $normalized,
                'display_name' => (string) ($dbHost->display_name ?: $normalized),
            ];
        }

        foreach ($this->hostsMap() as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            try {
                $normalized = $this->normalize((string) $key);
            } catch (FoundryException) {
                continue;
            }
            if (isset($hosts[$normalized])) {
                continue;
            }
            $hosts[$normalized] = [
                'host_url' => $normalized,
                'display_name' => (string) ($value['display_name'] ?? $normalized),
            ];
        }

        return array_values($hosts);
    }

    private function dbHost(string $normalizedHost): ?FoundryHostConfig
    {
        return FoundryHostConfig::query()
            ->where('enabled', true)
            ->where('host_url', $normalizedHost)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configuredHost(string $normalizedHost): ?array
    {
        foreach ($this->hostsMap() as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            try {
                $configuredHost = $this->normalize((string) $key);
            } catch (FoundryException) {
                continue;
            }
            if ($configuredHost === $normalizedHost) {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function hostsMap(): array
    {
        $hosts = config('foundry.hosts', []);

        return is_array($hosts) ? $hosts : [];
    }

    /**
     * @throws FoundryException
     */
    private function guardHost(string $host): void
    {
        if ($this->allowsInsecureHosts()) {
            return;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            throw FoundryException::hostNotAllowed('Local Foundry hosts are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPublic = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
            if ($isPublic === false) {
                throw FoundryException::hostNotAllowed('Private Foundry hosts are not allowed.');
            }
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
