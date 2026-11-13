<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\RouteStop;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A cover (ADR 0004): a stop the admin moved to the driver's route to cover an absent driver, marked by
 * `moved_from_route_id`. Its customer keeps its owner, but once the covering route is dispatched the driver's app
 * receives it with only what a visit needs (the customer, the address of its stop and its primary one, its balance, container
 * balances and agreements; never its preferences nor its other addresses), and the street records on it that day are not foreign.
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
     * The customers the driver covers through a stop that $stop matches (e.g. changed inside a window): a stop moved onto a
     * route already dispatched brings its customer whole, as the dispatch would have.
     *
     * @param  Builder<Customer>  $customers
     * @param  Closure(Builder<*>): mixed  $stop
     * @return Builder<Customer>
     */
    public static function customersByStop(Builder $customers, Driver $driver, Closure $stop): Builder
    {
        return $customers->whereHas('routeStops', fn (Builder $stops) => self::stops($stops, $driver)->where($stop));
    }

    /**
     * The addresses a covered customer travels with (S14): the address of its covered stop and its primary address.
     * The driver's own customers travel with every address; $route narrows the covering routes like in customers().
     *
     * @param  Builder<CustomerAddress>  $addresses
     * @param  (Closure(Builder<*>): mixed)|null  $route
     * @return Builder<CustomerAddress>
     */
    public static function visitAddresses(Builder $addresses, Driver $driver, ?Closure $route = null): Builder
    {
        return $addresses->where(fn (Builder $query) => $query
            ->whereHas('customer', fn (Builder $customer) => $customer->where('driver_id', $driver->id))
            ->orWhereIn('id', self::stops(RouteStop::query(), $driver, $route)->select('address_id'))
            ->orWhere(fn (Builder $primary) => $primary
                ->where('is_primary', true)
                ->whereHas('customer', fn (Builder $customer) => self::customers($customer, $driver, $route))));
    }

    /**
     * Whom a covered stop covers (`covered_driver_name`): the driver of the route it was moved from, the absent one, even
     * if the customer changed owner since; null for a stop never moved. Expects `movedFrom.driver.user` loaded.
     */
    public static function coveredDriverName(RouteStop $stop): ?string
    {
        return $stop->moved_from_route_id === null ? null : $stop->movedFrom?->driver?->user?->name;
    }

    /**
     * Marks a customer row the driver gets only through covered stops (`is_cover`): false for their own customer, even on a moved stop.
     */
    public static function mark(Customer $customer, Driver $driver): Customer
    {
        return $customer->setAttribute('is_cover', $customer->driver_id !== $driver->id);
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
