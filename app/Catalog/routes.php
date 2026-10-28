<?php

use App\Catalog\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Productos y precio de lista
    Route::middleware('can:manage-products')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::patch('/products/{product}/price', [ProductController::class, 'updatePrice'])->name('products.price');
    });
});
