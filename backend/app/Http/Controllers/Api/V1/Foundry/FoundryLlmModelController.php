<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Foundry;

use App\Http\Controllers\Controller;
use App\Http\Resources\FoundryLlmModelResource;
use App\Models\Diagram;
use App\Services\Foundry\DiagramAgentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Group;

#[Group('Foundry')]
class FoundryLlmModelController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly DiagramAgentService $agent) {}

    /** Enabled agent models available for the diagram's Foundry host. */
    public function index(Diagram $diagram): JsonResponse
    {
        $this->authorize('viewAgent', $diagram);

        $models = $this->agent->availableModels($diagram);

        return $this->success([
            'data' => FoundryLlmModelResource::collection($models)->resolve(),
            'default_model' => $this->agent->defaultModel($diagram)?->model,
            'enabled' => $this->agent->isEnabled(),
        ]);
    }
}
