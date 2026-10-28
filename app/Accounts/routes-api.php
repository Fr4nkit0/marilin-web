<?php

declare(strict_types=1);

use App\Accounts\Http\Controllers\Api\V1\AuthController;
use App\Accounts\Http\Middleware\RejectRefreshToken;
use Illuminate\Support\Facades\Route;

// Included from routes/api.php inside the v1 prefix.
Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware('auth:sanctum')
    ->name('api.v1.auth.refresh');

Route::middleware(['auth:sanctum', RejectRefreshToken::class])->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
});
