<?php

use App\Http\Controllers\CashCloseController;
use App\Http\Controllers\CustomerAgreementController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\HomePaymentController;
use App\Http\Controllers\HomeSaleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReviewInboxController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TruckLoadController;
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
    // Clientes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::middleware('can:manage-agreements')->scopeBindings()->group(function () {
        Route::post('/customers/{customer}/agreements', [CustomerAgreementController::class, 'store'])->name('customers.agreements.store');
        Route::patch('/customers/{customer}/agreements/{agreement}/end', [CustomerAgreementController::class, 'end'])->name('customers.agreements.end');
    });
    // Venta/Cobro en casa are recorded by the administrator (the same one who manages agreements, whose price the sale respects).
    Route::middleware('can:manage-agreements')->group(function () {
        Route::post('/customers/{customer}/home-sales', [HomeSaleController::class, 'store'])->name('customers.home-sales.store');
        Route::post('/customers/{customer}/home-payments', [HomePaymentController::class, 'store'])->name('customers.home-payments.store');
    });
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Choferes / Drivers
    Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
    Route::get('/drivers/{repartidor}', [DriverController::class, 'show'])->name('drivers.show');

    // Redirección de compatibilidad para /repartidores
    Route::get('/repartidores', fn () => redirect()->route('drivers.index'))->name('repartidores.index');
    Route::get('/repartidores/{repartidor}', fn ($repartidor) => redirect()->route('drivers.show', $repartidor))->name('repartidores.show');

    // Despacho y Rutas
    Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');
    Route::post('/routes/{route}/loads', [TruckLoadController::class, 'store'])
        ->middleware('can:load-trucks')
        ->name('routes.loads.store');

    // Ventas
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');

    // Cobros
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

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
