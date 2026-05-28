<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\DiagramChangelogResource;
use App\Models\Diagram;
use App\Models\DiagramChangelog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group("Diagrams")]
#[Subgroup("Changelog")]
class DiagramChangelogController extends Controller
{
    use AuthorizesRequests;

    /**
     * @throws AuthorizationException
     */
    public function index(Diagram $diagram, Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewChangelog', $diagram);

        $entries = DiagramChangelog::where('diagram_id', $diagram->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return DiagramChangelogResource::collection($entries);
    }

    public function store(Diagram $diagram, Request $request): JsonResponse
    {
        $this->authorize('addChangelog', $diagram);

        $user = $request->user();

        $validated = $request->validate([
            'action'  => 'required|string|max:100',
            'details' => 'nullable|array',
        ]);

        DiagramChangelog::create([
            'diagram_id' => $diagram->id,
            'user_id'    => $user->id,
            'user_name'  => $user->email,
            'action'     => $validated['action'],
            'details'    => $validated['details'] ?? null,
        ]);

        return response()->json(['status' => true]);
    }
}
