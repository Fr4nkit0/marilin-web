<?php

use App\Http\Controllers\CashCloseController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewInboxController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\SaleController;
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
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Clientes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Choferes / Drivers
    Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
    Route::get('/drivers/{repartidor}', [DriverController::class, 'show'])->name('drivers.show');

    // Redirección de compatibilidad para /repartidores
    Route::get('/repartidores', fn () => redirect()->route('drivers.index'))->name('repartidores.index');
    Route::get('/repartidores/{repartidor}', fn ($repartidor) => redirect()->route('drivers.show', $repartidor))->name('repartidores.show');

    // Despacho y Rutas
    Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');

    // Ventas
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');

    // Cobros
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

    // Revisión de lo subido por la app
    Route::get('/review', [ReviewInboxController::class, 'index'])->name('review.index');
    Route::patch('/review/{type}/{id}', [ReviewInboxController::class, 'markReviewed'])
        ->whereIn('type', ReviewInbox::TYPES)
        ->whereNumber('id')
        ->name('review.reviewed');

    // Cierre de Caja / Arqueo
    Route::get('/cash-close', [CashCloseController::class, 'index'])->name('cash-close.index');
});

require __DIR__.'/auth.php';
