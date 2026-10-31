<?php

use App\Review\Http\Controllers\CustomerChangeController;
use App\Review\Http\Controllers\ReviewInboxController;
use App\Review\ReviewInbox;
use Illuminate\Support\Facades\Route;

// Revisión de lo subido por la app
Route::get('/review', [ReviewInboxController::class, 'index'])->middleware('can:review-inbox')->name('review.index');
Route::patch('/review/{type}/{id}', [ReviewInboxController::class, 'markReviewed'])
    ->whereIn('type', ReviewInbox::markableTypes())
    ->whereNumber('id')
    ->middleware('can:review-inbox')
    ->name('review.reviewed');

// Corregir carga: a sale that left the truck below zero is fixed with a correction load and marked reviewed
Route::post('/review/sale/{id}/stock-correction', [ReviewInboxController::class, 'correctTruckLoad'])
    ->whereNumber('id')
    ->middleware(['can:review-inbox', 'can:load-trucks'])
    ->name('review.stock-correction');

// Cambios de un repartidor a clientes de otro: esperan la decisión del admin
Route::middleware('can:review-inbox')->group(function () {
    Route::post('/review/customer-changes/{change}/approve', [CustomerChangeController::class, 'approve'])->name('review.customer-changes.approve');
    Route::post('/review/customer-changes/{change}/reject', [CustomerChangeController::class, 'reject'])->name('review.customer-changes.reject');
});
