<?php

namespace App\Services;

use App\Models\Diagram;

class DiagramService
{
    public static function getUserDiagrams($request)
    {
        return $request->user()->diagrams()->get();
    }
    public static function getDiagramById($id)
    {
        return Diagram::find($id);
    }
}
