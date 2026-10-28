<?php

use App\Customers\Agreements\Http\Controllers\CustomerAgreementController;
use App\Customers\Customers\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Clientes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::middleware('can:manage-agreements')->scopeBindings()->group(function () {
        Route::post('/customers/{customer}/agreements', [CustomerAgreementController::class, 'store'])->name('customers.agreements.store');
        Route::patch('/customers/{customer}/agreements/{agreement}/end', [CustomerAgreementController::class, 'end'])->name('customers.agreements.end');
    });
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
});
