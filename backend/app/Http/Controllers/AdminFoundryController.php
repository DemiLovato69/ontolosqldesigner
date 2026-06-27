<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FoundryException;
use App\Models\FoundryHostConfig;
use App\Services\Foundry\FoundryHostConfigService;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Group;

#[Group('Admin')]
class AdminFoundryController extends Controller
{
    public function __construct(private readonly FoundryHostConfigService $hosts) {}

    public function index(): Factory|View
    {
        return view('admin.foundry', [
            'hosts' => FoundryHostConfig::orderBy('host_url')->get(),
            'envHosts' => $this->envHosts(),
            'allowCustomHosts' => $this->hosts->allowsCustomHosts(),
            'redirectUri' => (string) config('foundry.redirect_uri'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInput($request);

        $host = $this->normalizeOrBack($request, $data['host_url']);
        if ($host instanceof RedirectResponse) {
            return $host;
        }

        if (FoundryHostConfig::where('host_url', $host)->exists()) {
            return back()->withInput()->withErrors(['host_url' => 'That Foundry host is already configured.']);
        }

        FoundryHostConfig::create([
            'host_url' => $host,
            'display_name' => $data['display_name'],
            'client_id' => $data['client_id'],
            'client_secret' => $data['client_secret'],
            'enabled' => $data['enabled'],
        ]);

        return redirect()->route('admin.foundry')->with('status', 'Foundry host added.');
    }

    public function update(FoundryHostConfig $hostConfig, Request $request): RedirectResponse
    {
        $data = $this->validateInput($request);

        $host = $this->normalizeOrBack($request, $data['host_url']);
        if ($host instanceof RedirectResponse) {
            return $host;
        }

        if (FoundryHostConfig::where('host_url', $host)->whereKeyNot($hostConfig->id)->exists()) {
            return back()->withInput()->withErrors(['host_url' => 'Another host already uses that URL.']);
        }

        $attributes = [
            'host_url' => $host,
            'display_name' => $data['display_name'],
            'client_id' => $data['client_id'],
            'enabled' => $data['enabled'],
        ];

        // Secret handling: clear when requested, replace when provided, else keep.
        if ($request->boolean('clear_secret')) {
            $attributes['client_secret'] = null;
        } elseif ($data['client_secret'] !== null) {
            $attributes['client_secret'] = $data['client_secret'];
        }

        $hostConfig->update($attributes);

        return redirect()->route('admin.foundry')->with('status', 'Foundry host updated.');
    }

    public function destroy(FoundryHostConfig $hostConfig): RedirectResponse
    {
        $hostConfig->delete();

        return redirect()->route('admin.foundry')->with('status', 'Foundry host removed.');
    }

    /**
     * @return array{host_url: string, display_name: ?string, client_id: string, client_secret: ?string, enabled: bool}
     */
    private function validateInput(Request $request): array
    {
        $validated = $request->validate([
            'host_url' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:1024'],
            'enabled' => ['nullable', Rule::in(['0', '1', 'on', 'true', 'false'])],
        ]);

        return [
            'host_url' => (string) $validated['host_url'],
            'display_name' => $this->trimmedOrNull($validated['display_name'] ?? null),
            'client_id' => trim((string) $validated['client_id']),
            'client_secret' => $this->trimmedOrNull($validated['client_secret'] ?? null),
            'enabled' => $request->boolean('enabled'),
        ];
    }

    private function normalizeOrBack(Request $request, string $hostUrl): string|RedirectResponse
    {
        try {
            return $this->hosts->normalize($hostUrl);
        } catch (FoundryException $exception) {
            return back()->withInput()->withErrors(['host_url' => $exception->getMessage()]);
        }
    }

    private function trimmedOrNull(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * Env-configured hosts (read-only) shown for reference alongside DB hosts.
     *
     * @return list<array{host_url: string, display_name: string}>
     */
    private function envHosts(): array
    {
        $dbHosts = FoundryHostConfig::pluck('host_url')->all();

        return array_values(array_filter(
            $this->hosts->listConfiguredHosts(),
            static fn (array $host): bool => ! in_array($host['host_url'], $dbHosts, true),
        ));
    }
}
