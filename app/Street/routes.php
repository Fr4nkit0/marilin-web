<?php

use App\Street\Payments\Http\Controllers\HomePaymentController;
use App\Street\Payments\Http\Controllers\PaymentController;
use App\Street\RouteStock\Http\Controllers\TruckLoadController;
use App\Street\Sales\Http\Controllers\HomeSaleController;
use App\Street\Sales\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Venta/Cobro en casa are recorded by the administrator (the same one who manages agreements, whose price the sale respects).
    Route::middleware('can:manage-agreements')->group(function () {
        Route::post('/customers/{customer}/home-sales', [HomeSaleController::class, 'store'])->name('customers.home-sales.store');
        Route::post('/customers/{customer}/home-payments', [HomePaymentController::class, 'store'])->name('customers.home-payments.store');
    });

    Route::post('/routes/{route}/loads', [TruckLoadController::class, 'store'])
        ->middleware('can:load-trucks')
        ->name('routes.loads.store');

    // Ventas
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');

    // Cobros
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

    // Anular una venta o un cobro de cualquier día (con motivo, queda auditado y llega a la app en el pull)
    Route::middleware('can:cancel-street-records')->group(function () {
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
    });
});
