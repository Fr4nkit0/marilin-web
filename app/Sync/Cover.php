<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\RouteStop;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A cover (ADR 0004): a stop the admin moved to the driver's route to cover an absent driver, marked by
 * `moved_from_route_id`. Its customer keeps its owner, but once the covering route is dispatched the driver's app
 * receives it as if it were theirs, and the street records on it that day are not foreign.
 */
final class Cover
{
    /**
     * The enabled moved stops on the driver's dispatched routes; $route narrows those routes further (e.g. a date).
     *
     * @param  Builder<RouteStop>  $stops
     * @param  (Closure(Builder<*>): mixed)|null  $route
     * @return Builder<RouteStop>
     */
    public static function stops(Builder $stops, Driver $driver, ?Closure $route = null): Builder
    {
        return $stops
            ->where('enabled', true)
            ->whereNotNull('moved_from_route_id')
            ->whereHas('route', fn (Builder $query) => $query
                ->where('assigned_driver_id', $driver->id)
                ->whereNotNull('dispatched_at')
                ->when($route, $route));
    }

    /**
     * The customers the driver covers on a route that $route matches.
     *
     * @param  Builder<Customer>  $customers
     * @param  (Closure(Builder<*>): mixed)|null  $route
     * @return Builder<Customer>
     */
    public static function customers(Builder $customers, Driver $driver, ?Closure $route = null): Builder
    {
        return $customers->whereHas('routeStops', fn (Builder $stops) => self::stops($stops, $driver, $route));
    }

    /**
     * Whether the driver covers the customer on that day: what makes a street record of that day theirs.
     */
    public static function covers(Driver $driver, Customer $customer, string $date): bool
    {
        return self::stops(RouteStop::query()->where('customer_id', $customer->id), $driver, fn (Builder $route) => $route->whereDate('date', $date))
            ->exists();
    }
}
