<?php

use App\Customers\Customers\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

// Registered after the home-sales/home-payments routes so /customers/{customer} keeps its position.
Route::middleware('auth')->group(function () {
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
});
