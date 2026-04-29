<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
    Route::get('/github', [AuthController::class, 'redirectToGitHub']);
    Route::get('/github/callback', [AuthController::class, 'handleCallback']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('profiles')
    ->middleware(['auth:sanctum', 'access.token', 'throttle:api-user', 'api.version'])
    ->group(function (): void {
        Route::get('/', [ProfileController::class, 'index']);
        Route::get('/search', [ProfileController::class, 'search']);
        Route::get('/export', [ProfileController::class, 'export']);
        Route::post('/', [ProfileController::class, 'store'])->middleware('role:admin');
        Route::delete('/{profile}', [ProfileController::class, 'destroy'])->middleware('role:admin');
    });
