<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Diagram;
use App\Services\DiagramService;
use Illuminate\Http\Request;

class DiagramController extends Controller
{
    public function index(Request $request)
    {
        $diagrams = $request->user()->diagrams()->get();
        return view('diagrams.index', ['diagrams' => $diagrams]);
    }
    public function show($id)
    {
        $diagram = DiagramService::getDiagramById($id);
        return view('diagrams.show', ['diagram' => $diagram]);
    }

    public function addDiagram(Request $request)
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
    public function selectDiagram(Request $request)
    {
        return Diagram::where('id', $request->id)->get();
    }
    public function getDiagrams(Request $request)
    {
        return $request->user()->diagrams()->get();
    }
    public function saveDiagram(Request $request)
    {
        $diagram = Diagram::find($request->id);
        $diagram->update([
           'diagram' => $request->diagram
        ]);
        return response()->json([
            'message' => 'Diagram saved!'
        ]);
    }

}
