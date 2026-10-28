<?php

use App\Routes\Routes\Http\Controllers\DriverController;
use App\Routes\Routes\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Choferes / Drivers
    Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
    Route::get('/drivers/{repartidor}', [DriverController::class, 'show'])->name('drivers.show');

    // Redirección de compatibilidad para /repartidores
    Route::get('/repartidores', fn () => redirect()->route('drivers.index'))->name('repartidores.index');
    Route::get('/repartidores/{repartidor}', fn ($repartidor) => redirect()->route('drivers.show', $repartidor))->name('repartidores.show');

    // Despacho y Rutas
    Route::get('/routes', [RouteController::class, 'index'])->name('routes.index');
});
