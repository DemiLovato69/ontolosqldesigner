<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\DiagramService;
use Illuminate\Http\Request;

class DiagramController extends Controller  //TODO add a policy for this thing
{
    public function index(Request $request)
    {
        $diagrams = DiagramService::getUserDiagrams($request);
        return view('diagrams.index', ['diagrams' => $diagrams]);
    }
    public function show($id)
    {
        $diagram = DiagramService::getDiagramById($id);
        return view('diagrams.show', ['diagram' => $diagram]);
    }
}
