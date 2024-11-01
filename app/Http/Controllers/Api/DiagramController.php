<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiagramRequest;
use App\Models\Diagram;
use App\Services\DiagramService;
use Illuminate\Http\Request;
use \Illuminate\Http\JsonResponse;

class DiagramController extends Controller  //TODO add a policy for this thing
{
    public function index(Request $request)
    {
        return DiagramService::getUserDiagrams($request);
    }
    public function show($id)
    {
        return DiagramService::getDiagramById($id);
    }
    public function store(DiagramRequest $request): JsonResponse
    {
        Diagram::create([
            'name' => $request->name,
            'diagram' => NULL,
            'user_id' =>  $request->user()->id
        ]);
        return response()->json([
            'message' => 'Diagram created!'
        ]);
    }
    public function update(DiagramRequest $request, $id): JsonResponse
    {
        $diagram = DiagramService::getDiagramById($id);
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
}
