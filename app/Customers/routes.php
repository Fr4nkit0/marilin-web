<?php

use App\Customers\Agreements\Http\Controllers\CustomerAgreementController;
use App\Customers\Customers\Http\Controllers\CustomerController;
use App\Customers\Customers\Http\Controllers\CustomerPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Clientes
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::scopeBindings()->group(function () {
        Route::post('/customers/{customer}/preferences', [CustomerPreferenceController::class, 'store'])->name('customers.preferences.store');
        Route::put('/customers/{customer}/preferences/{preference}', [CustomerPreferenceController::class, 'update'])->name('customers.preferences.update');
        // Removing disables the preference, so the app hears about it in its next pull.
        Route::delete('/customers/{customer}/preferences/{preference}', [CustomerPreferenceController::class, 'destroy'])->name('customers.preferences.destroy');
    });
    Route::middleware('can:manage-agreements')->scopeBindings()->group(function () {
        Route::post('/customers/{customer}/agreements', [CustomerAgreementController::class, 'store'])->name('customers.agreements.store');
        Route::patch('/customers/{customer}/agreements/{agreement}/end', [CustomerAgreementController::class, 'end'])->name('customers.agreements.end');
    });
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
});
