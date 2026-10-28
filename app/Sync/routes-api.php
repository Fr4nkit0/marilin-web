<?php

use App\Sync\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('sync')->group(function (): void {
    Route::get('/bootstrap', [SyncController::class, 'bootstrap'])->name('api.v1.sync.bootstrap');
    Route::get('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
    Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
});
