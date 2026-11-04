<?php

use App\Catalog\Http\Controllers\ContainerTypeController;
use App\Catalog\Http\Controllers\PaymentMethodController;
use App\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Productos y precio de lista
    Route::middleware('can:manage-products')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::patch('/products/{product}/price', [ProductController::class, 'updatePrice'])->name('products.price');
    });

    // Configuración: tipos de envase y medios de pago. Nothing is deleted, a row is disabled; no code is editable.
    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/settings/container-types', [ContainerTypeController::class, 'index'])->name('container-types.index');
        Route::post('/settings/container-types', [ContainerTypeController::class, 'store'])->name('container-types.store');
        Route::patch('/settings/container-types/{containerType}', [ContainerTypeController::class, 'update'])->name('container-types.update');
        Route::patch('/settings/container-types/{containerType}/status', [ContainerTypeController::class, 'status'])->name('container-types.status');

        // No create: the cash close counts by the fixed codes CASH and TRANSFER.
        Route::get('/settings/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::patch('/settings/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::patch('/settings/payment-methods/{paymentMethod}/status', [PaymentMethodController::class, 'status'])->name('payment-methods.status');
    });
});
