<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CreateDiagramDTO;
use App\DTOs\ShareSettingsDTO;
use App\DTOs\UpdateDiagramDTO;
use App\Enums\DbType;
use App\Enums\DiagramAccess;
use App\Enums\ExportStatus;
use App\Enums\ImportStatus;
use App\Http\Requests\DiagramRequest;
use App\Http\Requests\SaveByTokenRequest;
use App\Http\Requests\UpdateShareAccessRequest;
use App\Http\Requests\UpdateVisitorAccessRequest;
use App\Http\Resources\DiagramResource;
use App\Http\Resources\DiagramSummaryResource;
use App\Http\Resources\DiagramVisitorResource;
use App\Models\Diagram;
use App\Models\DiagramInvite;
use App\Models\DiagramVisitor;
use App\Models\User;
use App\Services\DiagramCrudService;
use App\Services\DiagramSharingService;
use App\Services\DiagramSqlService;
use App\Services\OntologyMakerService;
use App\Support\DiagramSchema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group('Diagrams')]
class DiagramController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DiagramCrudService $crudService,
        private readonly DiagramSharingService $sharingService,
        private readonly DiagramSqlService $sqlService,
        private readonly OntologyMakerService $ontologyMakerService,
    ) {}

    #[Subgroup('CRUD')]
    public function index(Request $request): AnonymousResourceCollection
    {
        return DiagramSummaryResource::collection($this->crudService->getUserDiagrams($request->user()));
    }

    #[Subgroup('CRUD')]
    public function dashboard(Request $request): JsonResponse
    {
        $diagrams = $this->crudService->getDashboardDiagrams($request->user());

        return $this->success([
            'owned' => DiagramSummaryResource::collection($diagrams['owned'])->resolve($request),
            'shared' => DiagramSummaryResource::collection($diagrams['shared'])->resolve($request),
            'public' => DiagramSummaryResource::collection($diagrams['public'])->resolve($request),
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('CRUD')]
    public function show(Diagram $diagram): DiagramResource
    {
        $this->authorize('view', $diagram);

        return new DiagramResource($diagram);
    }

    #[Subgroup('CRUD')]
    public function store(DiagramRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $dto = new CreateDiagramDTO(
            name: $validated['name'],
            userId: $request->user()->id,
            dbType: DbType::tryFrom((string) ($validated['db_type'] ?? '')) ?? DbType::MYSQL,
            shareAccess: isset($validated['share_access']) ? DiagramAccess::from($validated['share_access']) : null,
            library: (bool) ($validated['library'] ?? false),
        );

        $diagram = $this->crudService->createDiagram($dto);

        return $this->created([
            'status' => true,
            'message' => 'Diagram created',
            'diagram' => [
                'id' => $diagram->id,
                'share_token' => $diagram->share_token,
            ],
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('CRUD')]
    public function update(Diagram $diagram, DiagramRequest $request): JsonResponse
    {
        $this->authorize('update', $diagram);

        $dto = new UpdateDiagramDTO(
            name: $request->has('name') ? (string) $request->input('name') : null,
            dbType: $request->has('db_type') ? DbType::from((string) $request->input('db_type')) : null,
            shareAccess: $request->has('share_access') ? DiagramAccess::from((string) $request->input('share_access')) : null,
            library: $request->has('library') ? (bool) $request->input('library') : null,
            schema: $request->exists('schema') ? $request->diagramSchema() : null,
            valueTypes: $request->has('value_types') ? $request->input('value_types', []) : null,
        );

        return $this->crudService->updateDiagram($diagram, $dto)
            ? $this->success(['status' => true, 'message' => 'Diagram saved'])
            : $this->success(['status' => false, 'message' => 'Failed saving the diagram']);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('CRUD')]
    public function destroy(Diagram $diagram): JsonResponse
    {
        $this->authorize('delete', $diagram);

        return $this->crudService->deleteDiagram($diagram)
            ? $this->noContent()
            : $this->success(['status' => false, 'message' => 'Failed deleting the diagram']);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('SQL')]
    public function import(Diagram $diagram, Request $request): JsonResponse
    {
        return $this->startImport($diagram, $request, 'sql');
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Import')]
    public function importFormat(string $format, Diagram $diagram, Request $request): JsonResponse
    {
        return $this->startImport($diagram, $request, $format);
    }

    /**
     * @throws AuthorizationException
     */
    private function startImport(Diagram $diagram, Request $request, string $format): JsonResponse
    {
        $this->authorize('import', $diagram);

        $script = $request->isJson()
            ? $request->input('script')
            : $request->getContent();
        if (! is_string($script) || $script === '') {
            return $this->success(['message' => 'The script field is required.'], 422);
        }

        /** @var User $user */
        $user = $request->user();
        $this->sqlService->startImport($diagram, $script, $user, $format);

        return $this->success(['status' => 'pending'], 202);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('SQL')]
    public function importStatus(Diagram $diagram): JsonResponse
    {
        $this->authorize('import', $diagram);

        return $this->success([
            'status' => $diagram->import_status,
            'schema' => $diagram->import_status === ImportStatus::DONE
                ? DiagramSchema::withoutRuntimeState($diagram->schema)
                : null,
            'value_types' => $diagram->import_status === ImportStatus::DONE
                ? ($diagram->value_types ?? [])
                : null,
            'db_type' => $diagram->import_status === ImportStatus::DONE
                ? ($diagram->db_type?->value ?? DbType::MYSQL->value)
                : null,
            'warnings' => $diagram->import_status === ImportStatus::DONE
                ? ($diagram->import_warnings ?? [])
                : [],
            'error' => $diagram->import_error,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('SQL')]
    public function export(Diagram $diagram, Request $request): JsonResponse
    {
        $this->authorize('export', $diagram);

        /** @var User $user */
        $user = $request->user();
        $this->sqlService->startExport($diagram, $user);

        return $this->success(['status' => 'pending'], 202);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('SQL')]
    public function exportStatus(Diagram $diagram): JsonResponse
    {
        $this->authorize('export', $diagram);

        return $this->success([
            'status' => $diagram->export_status,
            'script' => $diagram->export_status === ExportStatus::DONE ? $diagram->script : null,
            'json' => $diagram->export_status === ExportStatus::DONE ? $diagram->export_json : null,
            'error' => $diagram->export_error,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('SQL')]
    public function exportMigration(Diagram $diagram): Response
    {
        $this->authorize('export', $diagram);

        $zipPath = $this->sqlService->createMigrationZip($diagram);
        $content = file_get_contents($zipPath);
        unlink($zipPath);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $diagram->name).'_migrations.zip';

        return response($content, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('SQL')]
    public function exportJson(Diagram $diagram): JsonResponse
    {
        $this->authorize('export', $diagram);
        $diagram->refresh();

        return $this->success($this->sqlService->createJson(
            json_encode($diagram->schema),
            $diagram->value_types ?? [],
            $diagram->db_type?->value,
            $diagram->name
        ));
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Ontology')]
    public function exportOntology(Diagram $diagram): Response
    {
        $this->authorize('export', $diagram);
        $diagram->refresh();

        $module = $this->ontologyMakerService->createModule(
            json_encode($diagram->schema),
            $diagram->value_types ?? []
        );
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $diagram->name).'.mts';

        return response($module, 200, [
            'Content-Type' => 'text/typescript; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function share(Diagram $diagram): JsonResponse
    {
        $this->authorize('update', $diagram);

        return $this->success(['share_access' => $this->sharingService->ensureShared($diagram)]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function unshare(Diagram $diagram): JsonResponse
    {
        $this->authorize('update', $diagram);

        $this->sharingService->unshare($diagram);

        return $this->noContent();
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function updateShareAccess(Diagram $diagram, UpdateShareAccessRequest $request): JsonResponse
    {
        $this->authorize('update', $diagram);

        $validated = $request->validated();

        $dto = new ShareSettingsDTO(
            access: isset($validated['access']) ? DiagramAccess::from($validated['access']) : null,
            requireApproval: isset($validated['require_approval']) ? (bool) $validated['require_approval'] : null,
            library: isset($validated['library']) ? (bool) $validated['library'] : null,
        );

        return $this->success($this->sharingService->updateShareSettings($diagram, $dto));
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function getVisitors(Diagram $diagram): JsonResponse
    {
        $this->authorize('update', $diagram);

        return DiagramVisitorResource::collection($this->sharingService->getVisitors($diagram))->response();
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function getInvites(Diagram $diagram): JsonResponse
    {
        $this->authorize('update', $diagram);

        return $this->success($diagram->invites()
            ->orderBy('email')
            ->get(['id', 'email', 'access'])
            ->map(fn (DiagramInvite $invite) => [
                'id' => $invite->id,
                'email' => $invite->email,
                'access' => $invite->access?->value ?? DiagramAccess::READ->value,
            ])
            ->values());
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function updateInvites(Diagram $diagram, Request $request): JsonResponse
    {
        $this->authorize('update', $diagram);

        $validated = $request->validate([
            'invites' => ['present', 'array'],
            'invites.*.email' => ['required', 'email', 'max:255'],
            'invites.*.access' => ['required', Rule::in([DiagramAccess::READ->value, DiagramAccess::WRITE->value])],
        ]);

        $incoming = collect($validated['invites'])
            ->map(fn (array $invite) => [
                'email' => strtolower((string) $invite['email']),
                'access' => (string) $invite['access'],
            ])
            ->unique('email')
            ->values();

        $diagram->invites()
            ->whereNotIn('email', $incoming->pluck('email')->all())
            ->delete();

        foreach ($incoming as $invite) {
            $diagram->invites()->updateOrCreate(
                ['email' => $invite['email']],
                ['access' => $invite['access']]
            );
        }

        return $this->getInvites($diagram);
    }

    #[Subgroup('Sharing')]
    public function searchShareUsers(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (strlen($query) < 2) {
            return $this->success([]);
        }

        $needle = mb_strtolower($query);
        $users = User::query()
            ->whereRaw('LOWER(email) LIKE ?', ["%{$needle}%"])
            ->orderBy('email')
            ->limit(8)
            ->get(['email'])
            ->pluck('email')
            ->values();

        return $this->success($users);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function approveVisitor(Diagram $diagram, DiagramVisitor $visitor): JsonResponse
    {
        $this->authorize('update', $diagram);

        if ($visitor->diagram_id !== $diagram->id) {
            abort(404);
        }

        $visitor = $this->sharingService->approveVisitor($diagram, $visitor);

        return $this->success(['status' => true, 'access' => $visitor->access]);
    }

    /**
     * @throws AuthorizationException
     */
    #[Subgroup('Sharing')]
    public function updateVisitorAccess(Diagram $diagram, DiagramVisitor $visitor, UpdateVisitorAccessRequest $request): JsonResponse
    {
        $this->authorize('update', $diagram);

        if ($visitor->diagram_id !== $diagram->id) {
            abort(404);
        }

        $visitor = $this->sharingService->setVisitorAccess($diagram, $visitor, DiagramAccess::from($request->validated()['access']));

        return $this->success(['status' => true, 'visitor_status' => $visitor->status, 'access' => $visitor->access]);
    }

    #[Subgroup('Sharing')]
    public function saveByToken(string $token, SaveByTokenRequest $request): JsonResponse
    {
        $diagram = Diagram::where('share_token', $token)->firstOrFail();

        if (! $this->sharingService->saveByToken(
            $diagram,
            $request->user(),
            $request->diagramSchema() ?? [],
            $request->has('value_types') ? $request->input('value_types', []) : null
        )) {
            abort(403);
        }

        return $this->success(['status' => true]);
    }

    #[Subgroup('Sharing')]
    public function showEmbed(string $token): JsonResponse
    {
        $diagram = Diagram::where('share_token', $token)->firstOrFail();

        if (! $diagram->share_access) {
            abort(403, 'This diagram is not shared.');
        }

        return $this->success($this->crudService->getEmbedData($diagram));
    }

    #[Subgroup('Sharing')]
    public function showByToken(string $token, Request $request): DiagramResource|JsonResponse
    {
        $diagram = Diagram::where('share_token', $token)->firstOrFail();
        $result = $this->sharingService->resolveSharedAccess($diagram, $request->user());

        if ($result['status'] === 'not_shared') {
            abort(403, 'This diagram is not shared.');
        }
        if ($result['status'] === 'revoked') {
            return $this->success(['message' => 'Access revoked.'], 403);
        }
        if ($result['status'] === 'pending') {
            return $this->success(['pending_approval' => true], 403);
        }

        return new DiagramResource($result['diagram']);
    }

    #[Subgroup('Sharing')]
    public function duplicateByToken(string $token, Request $request): JsonResponse
    {
        $diagram = Diagram::where('share_token', $token)->firstOrFail();
        $result = $this->sharingService->resolveSharedAccess($diagram, $request->user());

        if ($result['status'] !== 'ok') {
            abort(403);
        }

        $copy = $this->crudService->duplicateForUser($diagram->fresh(), $request->user());

        return $this->created([
            'status' => true,
            'diagram' => [
                'id' => $copy->id,
                'share_token' => $copy->share_token,
            ],
        ]);
    }
}
