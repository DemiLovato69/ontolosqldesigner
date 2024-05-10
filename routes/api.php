<?php

use App\Http\Controllers\DiagramController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auth/register', [UserController::class, 'register']);
Route::post('/auth/login', [UserController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function ()
{
    Route::get('get-diagrams', [DiagramController::class, 'getDiagrams']);
    Route::post('select-diagram', [DiagramController::class, 'selectDiagram']);
    Route::post('save-diagram', [DiagramController::class, 'saveDiagram']);
    Route::post('add-diagram', [DiagramController::class, 'addDiagram']);

});
