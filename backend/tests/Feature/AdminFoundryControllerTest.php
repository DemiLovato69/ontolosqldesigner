<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FoundryHostConfig;
use App\Models\User;
use Tests\TestCase;

class AdminFoundryControllerTest extends TestCase
{
    public function test_index_requires_admin(): void
    {
        $this->get('/admin/foundry')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get('/admin/foundry')
            ->assertRedirect('/admin/login');
    }

    public function test_index_returns_ok_for_admin(): void
    {
        $this->asAdmin()->get('/admin/foundry')->assertStatus(200);
    }

    public function test_admin_can_add_a_host_and_url_is_normalized(): void
    {
        $this->asAdmin()
            ->post('/admin/foundry', [
                'host_url' => 'yourstack.palantirfoundry.com/foundry',
                'display_name' => 'Your Foundry',
                'client_id' => 'client-123',
                'client_secret' => '',
                'enabled' => '1',
            ])
            ->assertRedirect(route('admin.foundry'))
            ->assertSessionHas('status');

        $host = FoundryHostConfig::firstOrFail();
        $this->assertSame('https://yourstack.palantirfoundry.com', $host->host_url);
        $this->assertSame('client-123', $host->client_id);
        $this->assertNull($host->client_secret);
        $this->assertTrue($host->enabled);
    }

    public function test_adding_duplicate_host_is_rejected(): void
    {
        FoundryHostConfig::factory()->create(['host_url' => 'https://acme.palantirfoundry.com']);

        $this->asAdmin()
            ->post('/admin/foundry', [
                'host_url' => 'acme.palantirfoundry.com',
                'client_id' => 'client-x',
            ])
            ->assertSessionHasErrors('host_url');

        $this->assertSame(1, FoundryHostConfig::count());
    }

    public function test_invalid_host_is_rejected(): void
    {
        config()->set('foundry.allow_insecure_hosts', false);

        $this->asAdmin()
            ->post('/admin/foundry', [
                'host_url' => 'http://insecure.palantirfoundry.com',
                'client_id' => 'client-x',
            ])
            ->assertSessionHasErrors('host_url');

        $this->assertDatabaseCount('foundry_host_configs', 0);
    }

    public function test_update_keeps_secret_when_blank_and_replaces_when_provided(): void
    {
        $host = FoundryHostConfig::factory()->confidential()->create([
            'host_url' => 'https://acme.palantirfoundry.com',
            'client_id' => 'old-client',
        ]);
        $originalSecret = $host->client_secret;

        // Blank secret -> unchanged; other fields update.
        $this->asAdmin()->patch(route('admin.foundry.update', $host), [
            'host_url' => 'https://acme.palantirfoundry.com',
            'client_id' => 'new-client',
            'client_secret' => '',
            'enabled' => '1',
        ])->assertRedirect(route('admin.foundry'));

        $host->refresh();
        $this->assertSame('new-client', $host->client_id);
        $this->assertSame($originalSecret, $host->client_secret);

        // Provided secret -> replaced.
        $this->asAdmin()->patch(route('admin.foundry.update', $host), [
            'host_url' => 'https://acme.palantirfoundry.com',
            'client_id' => 'new-client',
            'client_secret' => 'rotated-secret',
            'enabled' => '1',
        ])->assertRedirect(route('admin.foundry'));

        $this->assertSame('rotated-secret', $host->refresh()->client_secret);
    }

    public function test_update_can_clear_secret_and_disable(): void
    {
        $host = FoundryHostConfig::factory()->confidential()->create([
            'host_url' => 'https://acme.palantirfoundry.com',
        ]);

        $this->asAdmin()->patch(route('admin.foundry.update', $host), [
            'host_url' => 'https://acme.palantirfoundry.com',
            'client_id' => $host->client_id,
            'client_secret' => '',
            'clear_secret' => '1',
            // enabled omitted -> disabled
        ])->assertRedirect(route('admin.foundry'));

        $host->refresh();
        $this->assertNull($host->client_secret);
        $this->assertFalse($host->enabled);
    }

    public function test_admin_can_delete_a_host(): void
    {
        $host = FoundryHostConfig::factory()->create();

        $this->asAdmin()
            ->delete(route('admin.foundry.destroy', $host))
            ->assertRedirect(route('admin.foundry'));

        $this->assertDatabaseMissing('foundry_host_configs', ['id' => $host->id]);
    }

    private function asAdmin(): self
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']));
    }
}
