<?php

use App\Http\Controllers\CashCloseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReviewInboxController;
use App\Services\Review\ReviewInbox;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    require __DIR__.'/../app/Customers/routes.php';

    require __DIR__.'/../app/Routes/routes.php';

    require __DIR__.'/../app/Street/routes.php';

    // Revisión de lo subido por la app
    Route::get('/review', [ReviewInboxController::class, 'index'])->middleware('can:review-inbox')->name('review.index');
    Route::patch('/review/{type}/{id}', [ReviewInboxController::class, 'markReviewed'])
        ->whereIn('type', ReviewInbox::TYPES)
        ->whereNumber('id')
        ->middleware('can:review-inbox')
        ->name('review.reviewed');

    // Cierre de Caja / Arqueo
    Route::get('/cash-close', [CashCloseController::class, 'index'])->name('cash-close.index');
    Route::get('/cash-close/export/{format}', [CashCloseController::class, 'export'])
        ->whereIn('format', ['pdf', 'xlsx'])
        ->name('cash-close.export');
});

require __DIR__.'/../app/Catalog/routes.php';
require __DIR__.'/../app/Accounts/routes.php';
