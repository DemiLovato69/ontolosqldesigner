<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Client\DiagramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('get-diagrams', [DiagramController::class, 'getDiagrams']);
    Route::post('select-diagram', [DiagramController::class, 'selectDiagram']);
    Route::post('save-diagram', [DiagramController::class, 'saveDiagram']);
    Route::post('add-diagram', [DiagramController::class, 'addDiagram']);
});
