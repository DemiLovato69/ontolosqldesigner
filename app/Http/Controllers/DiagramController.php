<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiagramController extends Controller
{
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
