<?php

namespace App\Http\Controllers;

use App\Accounts\Models\Driver;
use App\Catalog\Models\Product;
use App\Enums\RouteStatus;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteStop;
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
                'stops.rescheduledTo',
                'inventoryLoads' => fn ($query) => $query->orderBy('loaded_at')->orderBy('id'),
                'inventoryLoads.product',
                'inventoryLoads.creator',
                'inventories.product',
            ]);

        if ($statusFilter && $statusFilter !== 'all') {
            $routesQuery->where('status', $statusFilter);
        }

        if ($driverId) {
            $routesQuery->where('assigned_driver_id', $driverId);
        }

        $canLoad = $request->user()?->can('load-trucks') ?? false;

        $routes = $routesQuery->get()->map(function ($route) use ($canLoad) {
            $totalStops = $route->stops->count();
            $completedStops = $route->stops->filter(fn (RouteStop $stop) => $stop->isDone())->count();

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
                // Every load of the day (the first one and any reload) adds up per product.
                'truckStock' => $route->inventories->map(fn ($inv) => [
                    'productId' => $inv->product_id,
                    'productName' => $inv->product?->name ?? 'Producto',
                    'loaded' => (float) $route->inventoryLoads->where('product_id', $inv->product_id)->sum('quantity'),
                    'current' => (float) $inv->current_quantity,
                ])->values(),
                // The first load of the day is the one registered at the earliest moment; later ones are reloads.
                'loadHistory' => $route->inventoryLoads->map(fn ($load) => [
                    'id' => $load->id,
                    'isInitial' => $load->loaded_at?->equalTo($route->inventoryLoads->first()->loaded_at) ?? false,
                    'loadedAt' => $load->loaded_at?->format('H:i'),
                    'productName' => $load->product?->name ?? 'Producto',
                    'quantity' => (float) $load->quantity,
                    'loadedBy' => $load->creator?->name,
                ])->values(),
                'canLoad' => $canLoad && ! in_array($route->status, [RouteStatus::Completed->value, RouteStatus::Cancelled->value], true),
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
                    'status' => $stop->effectiveStatus()->value,
                    'done' => $stop->isDone(),
                    'arrivedAt' => $stop->visit?->arrived_at?->format('H:i'),
                    'outcome' => $stop->visit?->outcome?->name,
                    'notes' => $stop->visit?->notes,
                ]),
            ];
        });

        // Summary KPI Metrics
        $totalRoutes = $routes->count();
        $inProgressRoutes = $routes->where('status', RouteStatus::InProgress->value)->count();
        $completedRoutes = $routes->where('status', RouteStatus::Completed->value)->count();
        $totalPlannedStops = $routes->sum(fn ($r) => $r['stats']['totalStops']);
        $totalCompletedStops = $routes->sum(fn ($r) => $r['stats']['completedStops']);

        $drivers = Driver::with('user')->where('enabled', true)->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->user?->name ?? 'Chofer',
        ]);

        return Inertia::render('Routes/Index', [
            'routes' => $routes,
            'drivers' => $drivers,
            'products' => Product::query()->where('enabled', true)->orderBy('name')->get(['id', 'name']),
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
