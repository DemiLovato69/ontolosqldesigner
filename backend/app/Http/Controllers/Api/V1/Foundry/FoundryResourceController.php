<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Foundry;

use App\Exceptions\FoundryException;
use App\Http\Controllers\Controller;
use App\Models\Diagram;
use App\Services\Foundry\FoundryPlatformService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Group;

/**
 * Read-only Foundry Platform resources scoped through an ontology diagram. The
 * authenticated user's own connection for the diagram's host is always used.
 */
#[Group('Foundry')]
class FoundryResourceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FoundryPlatformService $platform,
    ) {}

    public function spaces(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $data = $this->platform->listSpaces($request->user(), $diagram, array_filter([
            'pageSize' => $this->pageSize($request),
            'pageToken' => $this->stringQuery($request, 'pageToken'),
        ], static fn ($value): bool => $value !== null));

        return $this->success($data);
    }

    public function folderChildren(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $rid = $this->stringQuery($request, 'rid')
            ?? $diagram->foundryConfig?->default_project_rid
            ?? $diagram->foundryConfig?->default_folder_rid;

        if (! is_string($rid) || $rid === '') {
            throw FoundryException::parameterRequired('rid');
        }

        $data = $this->platform->listFolderChildren($request->user(), $diagram, $rid, array_filter([
            'pageSize' => $this->pageSize($request),
            'pageToken' => $this->stringQuery($request, 'pageToken'),
        ], static fn ($value): bool => $value !== null));

        return $this->success($data);
    }

    public function ontologies(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        return $this->success($this->platform->listOntologies($request->user(), $diagram));
    }

    public function datasets(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $folderRid = $this->resolveFolderRid($request, $diagram);

        $data = $this->platform->listDatasets($request->user(), $diagram, array_filter([
            'folderRid' => $folderRid,
            'pageSize' => $this->pageSize($request),
            'pageToken' => $this->stringQuery($request, 'pageToken'),
        ], static fn ($value): bool => $value !== null));

        return $this->success($data);
    }

    public function dataset(Request $request, Diagram $diagram, string $datasetRid): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        return $this->success($this->platform->getDataset($request->user(), $diagram, $datasetRid));
    }

    public function datasetSchema(Request $request, Diagram $diagram, string $datasetRid): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        return $this->success($this->platform->getDatasetSchema(
            $request->user(),
            $diagram,
            $datasetRid,
            $this->stringQuery($request, 'branch'),
        ));
    }

    public function files(Request $request, Diagram $diagram, string $datasetRid): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $data = $this->platform->listFiles($request->user(), $diagram, $datasetRid, array_filter([
            'branch' => $this->stringQuery($request, 'branch'),
            'pathPrefix' => $this->stringQuery($request, 'pathPrefix'),
            'pageSize' => $this->pageSize($request),
            'pageToken' => $this->stringQuery($request, 'pageToken'),
        ], static fn ($value): bool => $value !== null));

        return $this->success($data);
    }

    public function file(Request $request, Diagram $diagram, string $datasetRid): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $path = $this->stringQuery($request, 'path');
        if ($path === null) {
            throw FoundryException::parameterRequired('path');
        }

        return $this->success($this->platform->getFile($request->user(), $diagram, $datasetRid, $path));
    }

    public function search(Request $request, Diagram $diagram): JsonResponse
    {
        $this->authorize('viewFoundry', $diagram);

        $path = $this->stringQuery($request, 'path');
        if ($path === null) {
            throw FoundryException::parameterRequired('path');
        }

        return $this->success($this->platform->search($request->user(), $diagram, $path));
    }

    private function resolveFolderRid(Request $request, Diagram $diagram): string
    {
        $folderRid = $this->stringQuery($request, 'folderRid')
            ?? $diagram->foundryConfig?->default_folder_rid
            ?? $diagram->foundryConfig?->default_project_rid;

        if (! is_string($folderRid) || $folderRid === '') {
            throw FoundryException::parameterRequired('folderRid');
        }

        return $folderRid;
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function pageSize(Request $request): ?int
    {
        $value = $request->query('pageSize');

        return is_numeric($value) ? (int) $value : null;
    }
}
