<?php

declare(strict_types=1);

use App\Accounts\Http\Middleware\RejectRefreshToken;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/../app/Accounts/routes-api.php';

    Route::middleware(['auth:sanctum', RejectRefreshToken::class])->group(function (): void {
        Route::prefix('sync')->group(function (): void {
            Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('api.v1.sync.bootstrap');
            Route::get('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
            Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
        });
    });
});
