<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiagramRequest;
use App\Models\Diagram;
use App\Repositories\DiagramRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DiagramController extends Controller  //TODO add a policy for this thing
{
    protected DiagramRepository $diagramRepository;

    public function __construct(DiagramRepository $diagramService)
    {
        $this->diagramRepository = $diagramService;
    }
    public function index(Request $request): JsonResponse
    {
        $diagrams = $this->diagramRepository->getUserDiagrams($request);

        return response()->json($diagrams);
    }

    public function show($id): JsonResponse
    {
        $diagram = $this->diagramRepository->getDiagramById($id);

        return response()->json($diagram);
    }

    public function store(DiagramRequest $request): JsonResponse
    {
        $this->diagramRepository->createDiagram($request);

        return response()->json([
            'message' => 'Diagram created!'
        ]);
    }

    public function update(DiagramRequest $request, $id): JsonResponse
    {
        $diagram = $this->diagramRepository->getDiagramById($id);
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
//        $diagram = DiagramRepository::getDiagramById($id);
//        $diagram->schema = json_encode(DiagramRepository::createSchemaFromScript(json_decode($script)));
//        $diagram->save();
//    }

    public function export($id): JsonResponse
    {
        $diagram = $this->diagramRepository->getDiagramById($id);
        $diagram->script = json_encode($this->diagramRepository->createScript($diagram->schema));
        $diagram->save();

        return response()->json($diagram->script);
    }
}
