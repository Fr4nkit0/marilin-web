<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Middleware\RejectRefreshToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('auth:sanctum')
        ->name('api.v1.auth.refresh');

    Route::middleware(['auth:sanctum', RejectRefreshToken::class])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        Route::prefix('sync')->group(function (): void {
            Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('api.v1.sync.bootstrap');
            Route::get('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
            Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
        });
    });
});
