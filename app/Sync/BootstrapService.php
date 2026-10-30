<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\CashDesk\DayScope;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitOutcome;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
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
            'visits' => $this->visits($driver, $today),
            'sales' => $this->sales($driver, $today),
            'payments' => $this->owned(Payment::query(), $driver, 'received_by', 'paid_at', $today),
            'container_movements' => $this->owned(ContainerMovement::query(), $driver, 'created_by', 'created_at', $today),
            'products' => Product::query()->where('enabled', true)->get(),
            'container_types' => ContainerType::query()->where('enabled', true)->get(),
            'payment_methods' => PaymentMethod::query()->where('enabled', true)->get(),
            'visit_outcomes' => VisitOutcome::query()->where('enabled', true)->get(),
            'neighborhoods' => Neighborhood::query()->where('enabled', true)->orderBy('name')->get(['id', 'name', 'enabled', 'updated_at']),
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
                'status' => $stop->effectiveStatus()->value,
                'enabled' => $stop->enabled,
            ])
            ->all();
    }

    /**
     * Today's visits of the driver, so a reinstalled app can rebuild its done work.
     *
     * @return Collection<int, Visit>
     */
    private function visits(Driver $driver, string $today): Collection
    {
        return Visit::query()
            ->where('driver_id', $driver->id)
            ->whereDate('arrived_at', $today)
            ->orderBy('arrived_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Today's sales of the driver with the ownership the cash close uses: on the driver's visits, or loose and recorded by the driver.
     *
     * @return Collection<int, Sale>
     */
    private function sales(Driver $driver, string $today): Collection
    {
        return Sale::query()
            ->with('details')
            ->tap(fn ($query) => DayScope::driver($driver)->apply($query, 'created_by'))
            ->whereDate('date', $today)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Today's records of the driver with the cash close ownership, cancelled ones included, so the app can rebuild what it settled.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    private function owned(Builder $query, Driver $driver, string $recordedByColumn, string $dateColumn, string $today): Collection
    {
        return $query
            ->tap(fn ($query) => DayScope::driver($driver)->apply($query, $recordedByColumn))
            ->whereDate($dateColumn, $today)
            ->orderBy($dateColumn)
            ->orderBy('id')
            ->get();
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
