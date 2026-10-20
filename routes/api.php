<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MapController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        Route::prefix('map')->group(function (): void {
            Route::get('/tiles/{z}/{x}/{y}.png', [MapController::class, 'tile'])->name('api.v1.map.tile');
            Route::get('/route', [MapController::class, 'route'])->name('api.v1.map.route');
            Route::get('/geocode', [MapController::class, 'geocode'])->name('api.v1.map.geocode');
        });

        Route::prefix('sync')->group(function (): void {
            Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('api.v1.sync.bootstrap');
            Route::get('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
            Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
        });
    });
});
