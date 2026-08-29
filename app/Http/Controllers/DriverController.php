<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Route as DeliveryRoute;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    /**
     * Display a listing of drivers with their daily routes and real-time operational status.
     */
    public function index(Request $request): Response
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $drivers = Driver::with([
            'user',
            'routes' => fn ($q) => $q->whereDate('date', $date)
                ->with(['stops.customer.primaryAddress', 'stops.visit', 'inventoryLoads.product', 'inventories.product']),
        ])
        ->where('enabled', true)
        ->get()
        ->map(function ($driver) use ($date) {
            $todayRoute = $driver->routes->first();

            $totalStops = $todayRoute ? $todayRoute->stops->count() : 0;
            $completedStops = $todayRoute ? $todayRoute->stops->filter(
                fn ($s) => $s->visit && in_array($s->visit->status, ['FINISHED', 'DELIVERED', 'COMPLETED'])
            )->count() : 0;

            $progress = $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0;

            return [
                'id' => $driver->id,
                'name' => $driver->user?->name ?? 'Sin nombre',
                'phone_number' => $driver->user?->phone_number,
                'email' => $driver->user?->email,
                'todayRoute' => $todayRoute ? [
                    'id' => $todayRoute->id,
                    'status' => $todayRoute->status,
                    'started_at' => $todayRoute->started_at?->format('H:i'),
                    'completed_at' => $todayRoute->completed_at?->format('H:i'),
                    'totalStops' => $totalStops,
                    'completedStops' => $completedStops,
                    'progressPercentage' => $progress,
                    'truckLoads' => $todayRoute->inventoryLoads->map(fn ($l) => [
                        'product' => $l->product?->name,
                        'quantity' => (float) $l->quantity,
                    ]),
                ] : null,
            ];
        });

        return Inertia::render('Repartidores/Index', [
            'drivers' => $drivers,
            'selectedDate' => $date,
        ]);
    }

    /**
     * Display a driver detailed profile, route timeline, and customer load.
     */
    public function show(Driver $repartidor, Request $request): Response
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $repartidor->load([
            'user',
            'customers.primaryAddress',
            'routes' => fn ($q) => $q->whereDate('date', $date)
                ->with([
                    'stops.customer.primaryAddress',
                    'stops.visit.outcome',
                    'inventoryLoads.product',
                    'inventories.product',
                ]),
        ]);

        $currentRoute = $repartidor->routes->first();

        return Inertia::render('Repartidores/Show', [
            'driver' => [
                'id' => $repartidor->id,
                'name' => $repartidor->user?->name ?? 'Sin nombre',
                'phone_number' => $repartidor->user?->phone_number,
                'email' => $repartidor->user?->email,
            ],
            'assignedCustomers' => $repartidor->customers,
            'currentRoute' => $currentRoute,
            'selectedDate' => $date,
        ]);
    }
}
