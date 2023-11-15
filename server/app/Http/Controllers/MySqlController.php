<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\Table;
use Illuminate\Http\Request;

class MySqlController extends Controller
{
    public function save(Request $request)
    {
        foreach ($request as $table)
        {
            $tableModel = new Table;

            $tableModel->name = $table->name;

            $tableModel->save();

            $fieldModel = new Field;
            foreach ($request->rows as $row)
            {

            }
        }
        return $request;
    }
}
