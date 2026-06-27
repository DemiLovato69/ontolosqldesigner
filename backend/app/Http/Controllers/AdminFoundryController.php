<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FoundryException;
use App\Models\FoundryHostConfig;
use App\Models\FoundryLlmModel;
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
            'models' => FoundryLlmModel::orderBy('host_url')->orderBy('sort_order')->orderBy('model')->get(),
            'llmEnabled' => (bool) config('foundry.llm.enabled', false),
            'llmEndpoint' => (string) config('foundry.llm.endpoint'),
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

    public function storeModel(Request $request): RedirectResponse
    {
        $data = $this->validateModelInput($request);

        $host = $this->normalizeModelHostOrBack($request, $data['host_url']);
        if ($host instanceof RedirectResponse) {
            return $host;
        }

        if (FoundryLlmModel::where('host_url', $host)->where('model', $data['model'])->exists()) {
            return back()->withInput()->withErrors(['model' => 'That model is already configured for this host.']);
        }

        $model = FoundryLlmModel::create([
            'host_url' => $host,
            'provider' => $data['provider'],
            'model' => $data['model'],
            'display_name' => $data['display_name'],
            'description' => $data['description'],
            'enabled' => $data['enabled'],
            'is_default' => $data['is_default'],
            'max_output_tokens' => $data['max_output_tokens'],
            'temperature' => $data['temperature'],
            'sort_order' => $data['sort_order'],
        ]);

        $this->syncDefault($model);

        return redirect()->route('admin.foundry')->with('status', 'Agent model added.');
    }

    public function updateModel(FoundryLlmModel $model, Request $request): RedirectResponse
    {
        $data = $this->validateModelInput($request);

        $host = $this->normalizeModelHostOrBack($request, $data['host_url']);
        if ($host instanceof RedirectResponse) {
            return $host;
        }

        if (FoundryLlmModel::where('host_url', $host)->where('model', $data['model'])->whereKeyNot($model->id)->exists()) {
            return back()->withInput()->withErrors(['model' => 'Another entry already uses that host + model.']);
        }

        $model->update([
            'host_url' => $host,
            'provider' => $data['provider'],
            'model' => $data['model'],
            'display_name' => $data['display_name'],
            'description' => $data['description'],
            'enabled' => $data['enabled'],
            'is_default' => $data['is_default'],
            'max_output_tokens' => $data['max_output_tokens'],
            'temperature' => $data['temperature'],
            'sort_order' => $data['sort_order'],
        ]);

        $this->syncDefault($model);

        return redirect()->route('admin.foundry')->with('status', 'Agent model updated.');
    }

    public function destroyModel(FoundryLlmModel $model): RedirectResponse
    {
        $model->delete();

        return redirect()->route('admin.foundry')->with('status', 'Agent model removed.');
    }

    /**
     * @return array{host_url: string, provider: string, model: string, display_name: ?string, description: ?string, enabled: bool, is_default: bool, max_output_tokens: ?int, temperature: ?float, sort_order: int}
     */
    private function validateModelInput(Request $request): array
    {
        $validated = $request->validate([
            'host_url' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'host_url' => trim((string) ($validated['host_url'] ?? '')),
            'provider' => trim((string) ($validated['provider'] ?? '')) ?: 'openai',
            'model' => trim((string) $validated['model']),
            'display_name' => $this->trimmedOrNull($validated['display_name'] ?? null),
            'description' => $this->trimmedOrNull($validated['description'] ?? null),
            'enabled' => $request->boolean('enabled'),
            'is_default' => $request->boolean('is_default'),
            'max_output_tokens' => isset($validated['max_output_tokens']) ? (int) $validated['max_output_tokens'] : null,
            'temperature' => isset($validated['temperature']) ? (float) $validated['temperature'] : null,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : 0,
        ];
    }

    private function normalizeModelHostOrBack(Request $request, string $hostUrl): string|RedirectResponse|null
    {
        if ($hostUrl === '') {
            return null;
        }

        try {
            return $this->hosts->normalize($hostUrl);
        } catch (FoundryException $exception) {
            return back()->withInput()->withErrors(['host_url' => $exception->getMessage()]);
        }
    }

    /**
     * Keep at most one default per host scope (matching host_url, including the
     * global null scope).
     */
    private function syncDefault(FoundryLlmModel $model): void
    {
        if (! $model->is_default) {
            return;
        }

        FoundryLlmModel::query()
            ->where('host_url', $model->host_url)
            ->whereKeyNot($model->id)
            ->update(['is_default' => false]);
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
