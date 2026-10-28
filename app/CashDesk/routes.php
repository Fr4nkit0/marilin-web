<?php

use App\CashDesk\Http\Controllers\CashCloseController;
use Illuminate\Support\Facades\Route;

// Cierre de Caja / Arqueo
Route::get('/cash-close', [CashCloseController::class, 'index'])->name('cash-close.index');
Route::get('/cash-close/export/{format}', [CashCloseController::class, 'export'])
    ->whereIn('format', ['pdf', 'xlsx'])
    ->name('cash-close.export');
