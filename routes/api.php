<?php

declare(strict_types=1);

use App\Accounts\Http\Middleware\RejectRefreshToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/../app/Accounts/routes-api.php';

    Route::middleware(['auth:sanctum', RejectRefreshToken::class])->group(function (): void {
        require __DIR__.'/../app/Sync/routes-api.php';
    });
});
