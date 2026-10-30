<?php

namespace App\CashDesk\Http\Controllers;

use App\Accounts\Models\Driver;
use App\CashDesk\CashCloseExport;
use App\CashDesk\CashCloseReport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as FileResponse;

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

        return Inertia::render('CashDesk/Index', [
            'repartidores' => $drivers->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->user?->name ?? 'Repartidor',
            ]),
            'selectedRepartidorId' => $selectedDriver?->id,
            'settlement' => $settlement,
            'date' => $date,
        ]);
    }

    /**
     * Download the cash close of one driver and day as PDF or Excel; a day without a route has nothing to export.
     */
    public function export(Request $request, string $format, CashCloseReport $report, CashCloseExport $export): FileResponse
    {
        $validated = $request->validate([
            'repartidor_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $driver = Driver::with('user')->findOrFail($validated['repartidor_id']);
        $settlement = $report->forDriver($driver, $validated['date']);
        abort_if($settlement === null, 404);

        $name = $driver->user?->name ?? 'Repartidor';

        return $format === 'pdf'
            ? $export->pdf($name, $validated['date'], $settlement)
            : $export->xlsx($name, $validated['date'], $settlement);
    }
}
