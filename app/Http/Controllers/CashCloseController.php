<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Services\CashClose\CashCloseReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashCloseController extends Controller
{
    /**
     * Display cash close / route settlement and reconciliation per driver.
     */
    public function index(Request $request, CashCloseReport $report): Response
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $driverId = $request->query('repartidor_id') ?? $request->query('driver_id');

        $drivers = Driver::with('user')->where('enabled', true)->get();

        // Selected driver or first driver
        $selectedDriver = $driverId
            ? $drivers->firstWhere('id', $driverId)
            : $drivers->first();

        $settlement = $selectedDriver === null ? null : $report->forDriver($selectedDriver, $date);

        return Inertia::render('CashClose/Index', [
            'repartidores' => $drivers->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user?->name ?? 'Repartidor',
            ]),
            'selectedRepartidorId' => $selectedDriver?->id,
            'settlement' => $settlement,
            'date' => $date,
        ]);
    }
}
