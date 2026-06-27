<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Foundry;

use App\Exceptions\FoundryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDiagramFoundryConfigRequest;
use App\Http\Resources\DiagramFoundryConfigResource;
use App\Models\Diagram;
use App\Models\DiagramFoundryConfig;
use App\Services\Foundry\FoundryHostConfigService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Group;

#[Group('Foundry')]
class DiagramFoundryConfigController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FoundryHostConfigService $hosts,
    ) {}

    public function show(Diagram $diagram): DiagramFoundryConfigResource
    {
        $this->authorize('viewFoundry', $diagram);
        $this->ensureOntology($diagram);

        $config = $diagram->foundryConfig ?? new DiagramFoundryConfig(['diagram_id' => $diagram->id]);

        return new DiagramFoundryConfigResource($config);
    }

    /**
     * Owner-only. Diagram owners may enter any valid HTTPS host; whether it can
     * be connected to depends on admin host configuration (hybrid model).
     */
    public function update(Diagram $diagram, UpdateDiagramFoundryConfigRequest $request): JsonResponse
    {
        $this->authorize('manageFoundry', $diagram);
        $this->ensureOntology($diagram);

        $validated = $request->validated();

        $attributes = [];

        if ($request->has('host_url')) {
            $host = $validated['host_url'] ?? null;
            $attributes['host_url'] = is_string($host) && trim($host) !== ''
                ? $this->hosts->normalize($host)
                : null;
        }

        foreach (['default_project_rid', 'default_folder_rid', 'default_ontology_rid'] as $key) {
            if ($request->has($key)) {
                $value = $validated[$key] ?? null;
                $attributes[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            }
        }

        if ($request->has('settings')) {
            $attributes['settings'] = $validated['settings'] ?? null;
        }

        $config = DiagramFoundryConfig::updateOrCreate(
            ['diagram_id' => $diagram->id],
            $attributes,
        );

        return (new DiagramFoundryConfigResource($config->refresh()))
            ->response()
            ->setStatusCode(200);
    }

    private function ensureOntology(Diagram $diagram): void
    {
        if (! $diagram->isOntology()) {
            throw FoundryException::diagramNotOntology();
        }
    }
}
