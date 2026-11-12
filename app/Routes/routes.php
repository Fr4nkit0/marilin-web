<?php

use App\Routes\Routes\Http\Controllers\DriverController;
use App\Routes\Routes\Http\Controllers\RouteController;
use App\Routes\Routes\Http\Controllers\RouteDispatchController;
use App\Routes\Routes\Http\Controllers\RouteStopMoveController;
use App\Routes\ScheduledVisits\Http\Controllers\ScheduledVisitController;
use App\Routes\Visits\Http\Controllers\VisitOutcomeController;
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

    // Despachar: whoever loads the truck releases the route to the app once it is loaded.
    Route::post('/routes/{route}/dispatch', [RouteDispatchController::class, 'store'])
        ->middleware('can:load-trucks')
        ->name('routes.dispatch');

    // Cobertura: whoever loads and dispatches the trucks splits an absent driver's route before dispatch.
    Route::post('/routes/{route}/stops/move', [RouteStopMoveController::class, 'store'])
        ->middleware('can:load-trucks')
        ->name('routes.stops.move');

    // Visitas agendadas: the admin asks the customer's driver to come by on a date.
    Route::post('/customers/{customer}/scheduled-visits', [ScheduledVisitController::class, 'store'])->name('scheduled-visits.store');
    Route::patch('/scheduled-visits/{scheduledVisit}/cancel', [ScheduledVisitController::class, 'cancel'])->name('scheduled-visits.cancel');

    // Configuración: resultados de visita. Nothing is deleted, an outcome is disabled; its code is never editable.
    Route::middleware('can:manage-settings')->group(function () {
        Route::get('/settings/visit-outcomes', [VisitOutcomeController::class, 'index'])->name('visit-outcomes.index');
        Route::post('/settings/visit-outcomes', [VisitOutcomeController::class, 'store'])->name('visit-outcomes.store');
        Route::patch('/settings/visit-outcomes/{visitOutcome}', [VisitOutcomeController::class, 'update'])->name('visit-outcomes.update');
        Route::patch('/settings/visit-outcomes/{visitOutcome}/status', [VisitOutcomeController::class, 'status'])->name('visit-outcomes.status');
    });
});
