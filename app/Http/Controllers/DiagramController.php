<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiagramRequest;
use App\Models\Diagram;
use App\Services\DiagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DiagramController extends Controller  //TODO add a policy for this thing
{
    protected DiagramService $diagramService;

    public function __construct(DiagramService $diagramService)
    {
        $this->diagramService = $diagramService;
    }
    public function index(Request $request): JsonResponse
    {
        $diagrams = $this->diagramService->getUserDiagrams($request);

        return response()->json($diagrams);
    }

    public function show($id): JsonResponse
    {
        $diagram = $this->diagramService->getDiagramById($id);

        return response()->json($diagram);
    }

    public function store(DiagramRequest $request): JsonResponse
    {
        $this->diagramService->createDiagram($request);

        return response()->json([
            'message' => 'Diagram created!'
        ]);
    }

    public function update(DiagramRequest $request, $id): JsonResponse
    {
        $diagram = $this->diagramService->getDiagramById($id);
        $diagram->update($request->all());

        return response()->json([
            'message' => 'Diagram updated!'
        ]);
    }

    public function destroy($id): JsonResponse
    {
        Diagram::destroy($id);

        return response()->json([
            'message' => 'Diagram deleted!'
        ]);
    }

//    public function import($id, Request $request)
//    {
//        $script = $request->input("script");
//        $diagram = DiagramService::getDiagramById($id);
//        $diagram->schema = json_encode(DiagramService::createSchemaFromScript(json_decode($script)));
//        $diagram->save();
//    }

    public function export($id): JsonResponse
    {
        $diagram = $this->diagramService->getDiagramById($id);
        $diagram->script = json_encode($this->diagramService->createScript($diagram->schema));
        $diagram->save();

        return response()->json($diagram->script);
    }
}
