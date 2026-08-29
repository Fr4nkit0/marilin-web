<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Route as DeliveryRoute;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouteController extends Controller
{
    /**
     * Display the Dispatch & Daily Route Management Center.
     */
    public function index(Request $request): Response
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $statusFilter = $request->query('status'); // 'all', 'PLANNED', 'IN_PROGRESS', 'COMPLETED'
        $driverId = $request->query('driver_id');

        $routesQuery = DeliveryRoute::query()
            ->whereDate('date', $date)
            ->with([
                'driver.user',
                'stops.customer.primaryAddress',
                'stops.visit.outcome',
                'inventoryLoads.product',
                'inventories.product',
            ]);

        if ($statusFilter && $statusFilter !== 'all') {
            $routesQuery->where('status', $statusFilter);
        }

        if ($driverId) {
            $routesQuery->where('assigned_driver_id', $driverId);
        }

        $routes = $routesQuery->get()->map(function ($route) {
            $totalStops = $route->stops->count();
            $completedStops = $route->stops->filter(
                fn ($s) => $s->visit && in_array($s->visit->status, ['FINISHED', 'DELIVERED', 'COMPLETED'])
            )->count();

            $pendingStops = $totalStops - $completedStops;
            $progress = $totalStops > 0 ? round(($completedStops / $totalStops) * 100) : 0;

            return [
                'id' => $route->id,
                'status' => $route->status,
                'date' => $route->date?->format('d/m/Y'),
                'rawDate' => $route->date?->toDateString(),
                'startedAt' => $route->started_at?->format('H:i'),
                'completedAt' => $route->completed_at?->format('H:i'),
                'driver' => [
                    'id' => $route->driver?->id,
                    'name' => $route->driver?->user?->name ?? 'Sin Chofer',
                    'phone' => $route->driver?->user?->phone_number,
                ],
                'stats' => [
                    'totalStops' => $totalStops,
                    'completedStops' => $completedStops,
                    'pendingStops' => $pendingStops,
                    'progressPercent' => $progress,
                ],
                'truckLoads' => $route->inventoryLoads->map(fn ($load) => [
                    'id' => $load->id,
                    'productName' => $load->product?->name ?? 'Producto',
                    'initialQuantity' => (float) $load->quantity,
                ]),
                'currentStock' => $route->inventories->map(fn ($inv) => [
                    'id' => $inv->id,
                    'productName' => $inv->product?->name ?? 'Producto',
                    'currentQuantity' => (float) $inv->current_quantity,
                ]),
                'stops' => $route->stops->map(fn ($stop) => [
                    'id' => $stop->id,
                    'plannedSequence' => $stop->planned_sequence,
                    'customer' => [
                        'id' => $stop->customer?->id,
                        'name' => $stop->customer?->name ?? 'Cliente',
                        'address' => $stop->customer?->primaryAddress?->street ?? 'Sin dirección',
                        'phone' => $stop->customer?->phone_number,
                    ],
                    'status' => $stop->visit?->status ?? 'PENDING',
                    'arrivedAt' => $stop->visit?->arrived_at?->format('H:i'),
                    'outcome' => $stop->visit?->outcome?->name,
                    'notes' => $stop->visit?->notes,
                ]),
            ];
        });

        // Summary KPI Metrics
        $totalRoutes = $routes->count();
        $inProgressRoutes = $routes->where('status', 'IN_PROGRESS')->count();
        $completedRoutes = $routes->where('status', 'COMPLETED')->count();
        $totalPlannedStops = $routes->sum(fn ($r) => $r['stats']['totalStops']);
        $totalCompletedStops = $routes->sum(fn ($r) => $r['stats']['completedStops']);

        $drivers = Driver::with('user')->where('enabled', true)->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->user?->name ?? 'Chofer',
        ]);

        return Inertia::render('Routes/Index', [
            'routes' => $routes,
            'drivers' => $drivers,
            'selectedDate' => $date,
            'filters' => [
                'status' => $statusFilter ?? 'all',
                'driver_id' => $driverId,
            ],
            'kpis' => [
                'totalRoutes' => $totalRoutes,
                'inProgressRoutes' => $inProgressRoutes,
                'completedRoutes' => $completedRoutes,
                'totalPlannedStops' => $totalPlannedStops,
                'totalCompletedStops' => $totalCompletedStops,
                'globalProgress' => $totalPlannedStops > 0 ? round(($totalCompletedStops / $totalPlannedStops) * 100) : 0,
            ],
        ]);
    }
}
