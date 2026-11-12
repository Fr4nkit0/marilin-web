<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use App\Accounts\Models\Driver;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Repartir ruta": what the admin sees to split an absent driver's route by neighborhood. The route's stops still to be
 * made, grouped by the neighborhood of the stop's address, and next to each neighborhood the other drivers whose route
 * of the same date already goes there, so a neighborhood goes to whoever is around. The move itself is MoveRouteStops.
 */
final class RouteSplit
{
    public const string NO_NEIGHBORHOOD = 'Sin barrio';

    /**
     * @return array{
     *     route: array{id: int, driverName: string, date: string, rawDate: string},
     *     groups: array<int, array{
     *         neighborhood: ?string,
     *         name: string,
     *         stops: array<int, array{id: int, customer: string, street: ?string, neighborhood: ?string, scheduledTime: ?string}>,
     *         suggestions: array<int, array{driverId: int, driverName: string, stops: int}>,
     *     }>,
     *     drivers: array<int, array{id: int, name: string}>,
     * }
     */
    public function of(Route $route): array
    {
        $route->loadMissing('driver.user');
        $suggestions = $this->suggestions($route);

        $groups = $this->movableStops($route)
            ->groupBy(fn (RouteStop $stop): string => self::key($stop->address?->neighborhood))
            ->map(function (Collection $stops, string $key) use ($suggestions): array {
                $named = $key !== '';

                return [
                    'neighborhood' => $named ? trim((string) $stops->first()->address->neighborhood) : null,
                    'name' => $named ? trim((string) $stops->first()->address->neighborhood) : self::NO_NEIGHBORHOOD,
                    'stops' => $stops->map(fn (RouteStop $stop): array => [
                        'id' => $stop->id,
                        'customer' => $stop->customer?->name ?? 'Cliente',
                        'street' => $stop->address?->street,
                        'neighborhood' => self::key($stop->address?->neighborhood) === '' ? null : trim((string) $stop->address->neighborhood),
                        'scheduledTime' => $stop->scheduled_time === null ? null : substr((string) $stop->scheduled_time, 0, 5),
                    ])->values()->all(),
                    // Nobody "delivers" in an unknown neighborhood.
                    'suggestions' => $named ? ($suggestions[$key] ?? []) : [],
                ];
            })
            // Neighborhoods alphabetically, the stops without one last.
            ->sortBy([fn (array $a, array $b): int => [$a['neighborhood'] === null, mb_strtolower($a['name'])] <=> [$b['neighborhood'] === null, mb_strtolower($b['name'])]])
            ->values()
            ->all();

        return [
            'route' => [
                'id' => $route->id,
                'driverName' => $route->driver?->user?->name ?? 'Sin repartidor',
                'date' => $route->date->format('d/m/Y'),
                'rawDate' => $route->date->toDateString(),
            ],
            'groups' => $groups,
            'drivers' => $this->otherDrivers($route),
        ];
    }

    /**
     * The stops that can still go to another driver, in the order the driver would make them.
     *
     * @return Collection<int, RouteStop>
     */
    private function movableStops(Route $route): Collection
    {
        return $route->stops()
            ->enabled()
            ->notDone()
            // A stop left behind when its customer was reassigned cannot be moved (MoveRouteStops): its new owner visits it.
            ->where(fn ($stops) => $stops
                ->whereNotNull('moved_from_route_id')
                ->orWhereHas('customer', fn ($customer) => $customer->where('driver_id', $route->assigned_driver_id)))
            ->with(['customer:id,name', 'address:id,street,neighborhood'])
            ->get()
            ->sortBy([
                fn (RouteStop $a, RouteStop $b): int => [$a->scheduled_time === null, $a->scheduled_time] <=> [$b->scheduled_time === null, $b->scheduled_time],
                fn (RouteStop $a, RouteStop $b): int => [mb_strtolower($a->customer?->name ?? ''), $a->id] <=> [mb_strtolower($b->customer?->name ?? ''), $b->id],
            ])
            ->values();
    }

    /**
     * Per neighborhood key, the other enabled drivers whose open route of the same date has enabled stops there,
     * with how many, the busiest first.
     *
     * @return array<string, array<int, array{driverId: int, driverName: string, stops: int}>>
     */
    private function suggestions(Route $route): array
    {
        $neighborhood = 'lower(trim(customer_addresses.neighborhood))';

        return RouteStop::query()
            ->join('routes', 'routes.id', '=', 'route_stops.route_id')
            ->join('customer_addresses', 'customer_addresses.id', '=', 'route_stops.address_id')
            ->join('drivers', 'drivers.id', '=', 'routes.assigned_driver_id')
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->whereDate('routes.date', $route->date)
            ->where('routes.enabled', true)
            // Only a route that can still take stops: planned and not dispatched (see MoveRouteStops).
            ->where('routes.status', RouteStatus::Planned->value)
            ->whereNull('routes.dispatched_at')
            ->where('routes.assigned_driver_id', '!=', $route->assigned_driver_id)
            ->where('drivers.enabled', true)
            ->where('route_stops.enabled', true)
            ->whereRaw("coalesce({$neighborhood}, '') <> ''")
            ->groupBy('drivers.id', 'users.name', DB::raw($neighborhood))
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('users.name')
            ->orderBy('drivers.id')
            ->get([
                DB::raw("{$neighborhood} as neighborhood_key"),
                'drivers.id as driver_id',
                'users.name as driver_name',
                DB::raw('count(*) as stops_count'),
            ])
            ->groupBy('neighborhood_key')
            ->map(fn (Collection $rows): array => $rows->map(fn ($row): array => [
                'driverId' => (int) $row->driver_id,
                'driverName' => (string) $row->driver_name,
                'stops' => (int) $row->stops_count,
            ])->values()->all())
            ->all();
    }

    /**
     * Every enabled driver but the route's, who can take any neighborhood.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function otherDrivers(Route $route): array
    {
        return Driver::query()
            ->where('drivers.enabled', true)
            ->whereKeyNot($route->assigned_driver_id)
            ->join('users', 'users.id', '=', 'drivers.user_id')
            ->orderBy('users.name')
            ->orderBy('drivers.id')
            ->get(['drivers.id', 'users.name'])
            ->map(fn (Driver $driver): array => ['id' => $driver->id, 'name' => (string) $driver->name])
            ->all();
    }

    /**
     * The same neighborhood however it was written: trimmed and lower case; empty for none.
     */
    private static function key(?string $neighborhood): string
    {
        return mb_strtolower(trim((string) $neighborhood));
    }
}
