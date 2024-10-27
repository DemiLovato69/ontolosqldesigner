<?php

use App\Http\Controllers\DiagramController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/diagrams', [DiagramController::class, 'index'])->name('diagrams.index');
Route::get('/diagrams/{id}', [DiagramController::class, 'show'])->name('diagrams.show');
