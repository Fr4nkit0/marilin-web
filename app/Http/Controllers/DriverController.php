<?php

namespace App\Http\Controllers;

use App\Models\Driver;
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
            'customers',
            'routes' => fn ($q) => $q->whereDate('date', $date)
                ->with(['stops.customer.primaryAddress', 'stops.visit', 'inventoryLoads.product', 'inventories.product']),
        ])
            ->where('enabled', true)
            ->get()
            ->map(function ($driver) {
                $todayRoute = $driver->routes->first();

                $totalStops = $todayRoute ? $todayRoute->stops->count() : 0;
                $completedStops = $todayRoute ? $todayRoute->stops->filter(
                    fn ($s) => $s->visit && in_array($s->visit->status, ['FINISHED', 'DELIVERED', 'COMPLETED'])
                )->count() : 0;

                $progress = $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0;

                $routeData = $todayRoute ? [
                    'id' => $todayRoute->id,
                    'status' => $todayRoute->status,
                    'startedAt' => $todayRoute->started_at?->format('H:i'),
                    'completedAt' => $todayRoute->completed_at?->format('H:i'),
                    'totalStops' => $totalStops,
                    'completedStops' => $completedStops,
                    'progressPercent' => $progress,
                    'truckLoads' => $todayRoute->inventoryLoads->map(fn ($l) => [
                        'product' => $l->product?->name,
                        'quantity' => (float) $l->quantity,
                    ]),
                ] : null;

                return [
                    'id' => $driver->id,
                    'name' => $driver->user?->name ?? 'Sin nombre',
                    'phone_number' => $driver->user?->phone_number,
                    'email' => $driver->user?->email,
                    'totalCustomers' => $driver->customers->count(),
                    'currentRoute' => $routeData,
                    'todayRoute' => $routeData,
                ];
            });

        return Inertia::render('Drivers/Index', [
            'repartidores' => $drivers,
            'drivers' => $drivers,
            'filters' => [
                'search' => $request->query('search'),
            ],
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
                    'stops.visit.payments',
                    'stops.visit.sales',
                    'inventoryLoads.product',
                    'inventories.product',
                ]),
        ]);

        $currentRoute = $repartidor->routes->first();

        $formattedRoute = $currentRoute ? [
            'id' => $currentRoute->id,
            'date' => $currentRoute->date?->format('d/m/Y') ?? $date,
            'status' => $currentRoute->status,
            'startedAt' => $currentRoute->started_at?->format('H:i'),
            'completedAt' => $currentRoute->completed_at?->format('H:i'),
            'totalSalesAmount' => (float) $currentRoute->stops->sum(fn ($s) => $s->visit?->sales->sum('total') ?? 0),
            'totalCollectedAmount' => (float) $currentRoute->stops->sum(fn ($s) => $s->visit?->payments->sum('amount') ?? 0),
            'stops' => $currentRoute->stops->map(fn ($stop) => [
                'id' => $stop->id,
                'customer' => [
                    'id' => $stop->customer->id,
                    'name' => $stop->customer->name,
                    'address' => $stop->customer->primaryAddress?->street ?? 'Sin dirección',
                    'phone' => $stop->customer->phone_number,
                ],
                'status' => $stop->visit?->status ?? 'PENDING',
                'arrivedAt' => $stop->visit?->arrived_at?->format('H:i'),
                'salesAmount' => (float) ($stop->visit?->sales->sum('total') ?? 0),
                'paymentsAmount' => (float) ($stop->visit?->payments->sum('amount') ?? 0),
                'notes' => $stop->visit?->notes,
            ]),
            'inventory' => $currentRoute->inventories->map(fn ($i) => [
                'id' => $i->id,
                'productName' => $i->product?->name ?? 'Producto',
                'currentQuantity' => (float) $i->current_quantity,
            ]),
        ] : null;

        return Inertia::render('Drivers/Show', [
            'repartidor' => [
                'id' => $repartidor->id,
                'name' => $repartidor->user?->name ?? 'Sin nombre',
                'email' => $repartidor->user?->email,
                'totalAssignedCustomers' => $repartidor->customers->count(),
            ],
            'route' => $formattedRoute,
            'targetDate' => $date,
        ]);
    }
}
