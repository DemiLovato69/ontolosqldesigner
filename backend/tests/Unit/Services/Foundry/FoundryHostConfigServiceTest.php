<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Foundry;

use App\Exceptions\FoundryException;
use App\Models\FoundryHostConfig;
use App\Services\Foundry\FoundryHostConfigService;
use Tests\TestCase;

class FoundryHostConfigServiceTest extends TestCase
{
    private FoundryHostConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FoundryHostConfigService;
        config()->set('foundry.hosts', []);
        config()->set('foundry.allow_custom_hosts', false);
        config()->set('foundry.allow_insecure_hosts', false);
        config()->set('foundry.custom_host', ['client_id' => null, 'client_secret' => null]);
    }

    public function test_normalize_assumes_https_for_bare_domain_and_strips_path(): void
    {
        $this->assertSame(
            'https://acme.palantirfoundry.com',
            $this->service->normalize('acme.palantirfoundry.com/foundry/index?x=1'),
        );
    }

    public function test_normalize_preserves_port(): void
    {
        $this->assertSame(
            'https://acme.palantirfoundry.com:8443',
            $this->service->normalize('https://acme.palantirfoundry.com:8443/'),
        );
    }

    public function test_normalize_rejects_http_when_insecure_not_allowed(): void
    {
        $this->expectException(FoundryException::class);
        $this->service->normalize('http://acme.palantirfoundry.com');
    }

    public function test_normalize_rejects_private_host(): void
    {
        $this->expectException(FoundryException::class);
        $this->service->normalize('https://10.0.0.5');
    }

    public function test_resolve_client_returns_configured_host(): void
    {
        config()->set('foundry.hosts', [
            'https://acme.palantirfoundry.com' => [
                'client_id' => 'client-123',
                'client_secret' => 'secret-xyz',
                'display_name' => 'Acme Foundry',
            ],
        ]);

        $client = $this->service->resolveClient('acme.palantirfoundry.com');

        $this->assertSame('https://acme.palantirfoundry.com', $client['host_url']);
        $this->assertSame('client-123', $client['client_id']);
        $this->assertSame('secret-xyz', $client['client_secret']);
        $this->assertTrue($client['configured']);
    }

    public function test_resolve_client_throws_for_unconfigured_host_when_custom_disabled(): void
    {
        try {
            $this->service->resolveClient('https://unknown.palantirfoundry.com');
            $this->fail('Expected FoundryException.');
        } catch (FoundryException $exception) {
            $this->assertSame('foundry_host_not_configured', $exception->errorCode);
        }
    }

    public function test_resolve_client_allows_custom_host_with_custom_client(): void
    {
        config()->set('foundry.allow_custom_hosts', true);
        config()->set('foundry.custom_host', ['client_id' => 'public-client', 'client_secret' => null]);

        $client = $this->service->resolveClient('https://custom.palantirfoundry.com');

        $this->assertSame('public-client', $client['client_id']);
        $this->assertNull($client['client_secret']);
        $this->assertFalse($client['configured']);
    }

    public function test_resolve_client_throws_when_custom_allowed_but_no_client_configured(): void
    {
        config()->set('foundry.allow_custom_hosts', true);
        config()->set('foundry.custom_host', ['client_id' => null, 'client_secret' => null]);

        $this->expectException(FoundryException::class);
        $this->service->resolveClient('https://custom.palantirfoundry.com');
    }

    public function test_resolve_client_prefers_enabled_db_host_over_env(): void
    {
        config()->set('foundry.hosts', [
            'https://acme.palantirfoundry.com' => ['client_id' => 'env-client', 'display_name' => 'Env'],
        ]);
        FoundryHostConfig::factory()->confidential()->create([
            'host_url' => 'https://acme.palantirfoundry.com',
            'client_id' => 'db-client',
            'display_name' => 'DB Acme',
        ]);

        $client = $this->service->resolveClient('acme.palantirfoundry.com');

        $this->assertSame('db-client', $client['client_id']);
        $this->assertSame('DB Acme', $client['display_name']);
        $this->assertNotNull($client['client_secret']);
        $this->assertTrue($client['configured']);
    }

    public function test_disabled_db_host_is_not_connectable_without_fallback(): void
    {
        FoundryHostConfig::factory()->disabled()->create([
            'host_url' => 'https://acme.palantirfoundry.com',
            'client_id' => 'db-client',
        ]);

        try {
            $this->service->resolveClient('https://acme.palantirfoundry.com');
            $this->fail('Expected FoundryException.');
        } catch (FoundryException $exception) {
            $this->assertSame('foundry_host_not_configured', $exception->errorCode);
        }
    }

    public function test_list_configured_hosts_merges_db_and_env_without_duplicates(): void
    {
        config()->set('foundry.hosts', [
            'https://acme.palantirfoundry.com' => ['client_id' => 'env-client', 'display_name' => 'Env Acme'],
            'https://envonly.palantirfoundry.com' => ['client_id' => 'env-2', 'display_name' => 'Env Only'],
        ]);
        FoundryHostConfig::factory()->create([
            'host_url' => 'https://acme.palantirfoundry.com',
            'display_name' => 'DB Acme',
        ]);
        FoundryHostConfig::factory()->disabled()->create([
            'host_url' => 'https://disabled.palantirfoundry.com',
        ]);

        $hosts = collect($this->service->listConfiguredHosts())->keyBy('host_url');

        $this->assertSame('DB Acme', $hosts['https://acme.palantirfoundry.com']['display_name']);
        $this->assertArrayHasKey('https://envonly.palantirfoundry.com', $hosts->all());
        $this->assertArrayNotHasKey('https://disabled.palantirfoundry.com', $hosts->all());
        $this->assertCount(2, $hosts);
    }
}
