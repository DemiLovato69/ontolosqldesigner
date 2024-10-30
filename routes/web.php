<?php
use App\Http\Controllers\Client\AuthController;
use App\Http\Controllers\Client\DiagramController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/register', [AuthController::class, 'register'])->name('register');
Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/diagrams', [DiagramController::class, 'index'])->name('diagrams.index');
    Route::get('/diagrams/{id}', [DiagramController::class, 'show'])->name('diagrams.show');
});
