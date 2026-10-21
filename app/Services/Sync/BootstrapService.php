<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\VisitOutcome;
use Illuminate\Database\Eloquent\Collection;

class BootstrapService
{
    /**
     * Builds the full day of a driver: today's route, assigned customers and catalogs.
     *
     * @return array<string, mixed>
     */
    public function build(Driver $driver): array
    {
        $today = now()->toDateString();
        $route = $this->currentRoute($driver, $today);

        return [
            'route' => $route,
            'stops' => $route === null ? [] : $this->stops($route),
            'truck_inventory' => $route === null ? [] : $route->inventories()->with('product')->get(),
            'customers' => $this->customers($driver, $today),
            'products' => Product::query()->where('enabled', true)->get(),
            'container_types' => ContainerType::query()->where('enabled', true)->get(),
            'payment_methods' => PaymentMethod::query()->where('enabled', true)->get(),
            'visit_outcomes' => VisitOutcome::query()->where('enabled', true)->get(),
            'server_time' => now()->startOfSecond()->subSeconds((int) config('sync.pull_cursor_margin_seconds'))->toIso8601String(),
        ];
    }

    private function currentRoute(Driver $driver, string $today): ?Route
    {
        // Only today's route: the app shows the day's route, and stops left unvisited on
        // earlier days go to the review inbox (review:flag-unvisited-stops).
        return Route::query()
            ->where('assigned_driver_id', $driver->id)
            ->whereDate('date', $today)
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stops(Route $route): array
    {
        return $route->stops()
            ->whereHas('customer', fn ($query) => $query
                ->where('driver_id', $route->assigned_driver_id)
                ->where('enabled', true))
            ->with(['visit', 'rescheduledTo'])
            ->orderByRaw('scheduled_time asc nulls first')
            ->orderBy('id')
            ->get()
            ->map(fn (RouteStop $stop): array => [
                'id' => $stop->id,
                'route_id' => $stop->route_id,
                'customer_id' => $stop->customer_id,
                'address_id' => $stop->address_id,
                'scheduled_time' => $stop->scheduled_time,
                'is_rescheduled_from' => $stop->is_rescheduled_from,
                'status' => $stop->effectiveStatus(),
                'enabled' => $stop->enabled,
            ])
            ->all();
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(Driver $driver, string $today): Collection
    {
        return Customer::query()
            ->where('driver_id', $driver->id)
            ->where('enabled', true)
            ->with([
                'addresses',
                'preferences',
                'agreements' => fn ($query) => $query->active($today)->with('product'),
                'balance',
                'containerBalances.containerType',
            ])
            ->get();
    }
}
