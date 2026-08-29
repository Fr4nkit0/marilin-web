<?php

use App\Http\Controllers\ProfileController;
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

Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Clientes
    Route::get('/customers', [\App\Http\Controllers\CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [\App\Http\Controllers\CustomerController::class, 'show'])->name('customers.show');

    // Repartidores
    Route::get('/repartidores', [\App\Http\Controllers\RepartidorController::class, 'index'])->name('repartidores.index');
    Route::get('/repartidores/{repartidor}', [\App\Http\Controllers\RepartidorController::class, 'show'])->name('repartidores.show');

    // Ventas
    Route::get('/sales', [\App\Http\Controllers\SaleController::class, 'index'])->name('sales.index');

    // Cobros
    Route::get('/payments', [\App\Http\Controllers\PaymentController::class, 'index'])->name('payments.index');

    // Cierre de Caja / Arqueo
    Route::get('/cash-close', [\App\Http\Controllers\CashCloseController::class, 'index'])->name('cash-close.index');
});

require __DIR__.'/auth.php';
