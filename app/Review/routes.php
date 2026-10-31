<?php

use App\Review\Http\Controllers\ReviewInboxController;
use App\Review\ReviewInbox;
use Illuminate\Support\Facades\Route;

// Revisión de lo subido por la app
Route::get('/review', [ReviewInboxController::class, 'index'])->middleware('can:review-inbox')->name('review.index');
Route::patch('/review/{type}/{id}', [ReviewInboxController::class, 'markReviewed'])
    ->whereIn('type', ReviewInbox::TYPES)
    ->whereNumber('id')
    ->middleware('can:review-inbox')
    ->name('review.reviewed');

// Corregir carga: a sale that left the truck below zero is fixed with a correction load and marked reviewed
Route::post('/review/sale/{id}/stock-correction', [ReviewInboxController::class, 'correctTruckLoad'])
    ->whereNumber('id')
    ->middleware(['can:review-inbox', 'can:load-trucks'])
    ->name('review.stock-correction');
