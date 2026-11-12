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
 * "Cobertura": when a driver misses a day, the admin moves part of their route to another driver's route of the same date,
 * created planned if it does not exist yet. The same stop rows move, so their scheduled visits and reschedule links stay,
 * and each one remembers the absent driver's route it came from. The customers keep their owner.
 * Only before dispatch: once the app has a route, its stops stay where they are.
 */
final class MoveRouteStops
{
    /**
     * @param  array<int, int>  $stopIds
     * @return Route the destination route
     *
     * @throws InvalidRouteStopMove
     */
    public function move(Route $source, array $stopIds, Driver $destinationDriver): Route
    {
        if ($destinationDriver->id === $source->assigned_driver_id) {
            throw new InvalidRouteStopMove('Elegí un repartidor distinto al de la ruta.', 'driver_id');
        }

        return DB::transaction(function () use ($source, $stopIds, $destinationDriver): Route {
            $existing = Route::query()
                ->where('assigned_driver_id', $destinationDriver->id)
                ->whereDate('date', $source->date)
                ->value('id');

            // Locked in id order, so two moves between the same routes in opposite directions never deadlock;
            // a dispatch waits for the move and then sees the stops where the move left them.
            $locked = Route::query()
                ->whereKey(array_filter([$source->id, $existing]))
                ->orderBy('id')
                ->lock('for no key update')
                ->get()
                ->keyBy('id');

            $source = $locked->get($source->id);
            $this->ensureSourceLends($source);

            // A driver disabled meanwhile gets no stops.
            if (! Driver::query()->whereKey($destinationDriver->id)->enabled()->exists()) {
                throw new InvalidRouteStopMove('Elegí un repartidor habilitado.', 'driver_id');
            }

            $stops = $this->stopsToMove($source, $stopIds);

            $destination = $existing === null
                ? Route::query()->createOrFirst(
                    ['assigned_driver_id' => $destinationDriver->id, 'date' => $source->date->toDateString()],
                    ['status' => RouteStatus::Planned->value, 'enabled' => true],
                )
                : $locked->get($existing);

            $this->ensureDestinationTakes($destination, $destinationDriver);
            $this->ensureNoCustomerTwice($destination, $stops, $destinationDriver);

            $stops->each(function (RouteStop $stop) use ($source, $destination): void {
                // A stop moved on keeps the absent driver's route; moved back there, it is no longer a cover.
                $movedFrom = $stop->moved_from_route_id ?? $source->id;

                $stop->update([
                    'route_id' => $destination->id,
                    'moved_from_route_id' => $movedFrom === $destination->id ? null : $movedFrom,
                ]);
            });

            return $destination;
        });
    }

    private function ensureSourceLends(Route $source): void
    {
        if ($source->dispatched_at !== null) {
            throw new InvalidRouteStopMove('La ruta ya fue despachada: no se pueden mover sus paradas.');
        }

        if ($source->status !== RouteStatus::Planned->value) {
            throw new InvalidRouteStopMove('Solo se pueden mover paradas de una ruta planificada.');
        }
    }

    private function ensureDestinationTakes(Route $destination, Driver $driver): void
    {
        $name = $driver->user?->name ?? 'el repartidor';

        if ($destination->dispatched_at !== null) {
            throw new InvalidRouteStopMove("La ruta de {$name} ya fue despachada.");
        }

        if (in_array($destination->status, [RouteStatus::Completed->value, RouteStatus::Cancelled->value], true) || ! $destination->enabled) {
            throw new InvalidRouteStopMove("La ruta de {$name} está cerrada.");
        }
    }

    /**
     * Every selected stop, locked, as long as all of them are on the source route and still to be made.
     *
     * @param  array<int, int>  $stopIds
     * @return Collection<int, RouteStop>
     */
    private function stopsToMove(Route $source, array $stopIds)
    {
        $ids = array_values(array_unique($stopIds));
        $stops = RouteStop::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if ($stops->count() !== count($ids) || $stops->contains(fn (RouteStop $stop): bool => $stop->route_id !== $source->id)) {
            throw new InvalidRouteStopMove('Alguna de las paradas no es de esta ruta.');
        }

        // A done, rescheduled or disabled stop has nothing left to cover.
        if ($source->stops()->whereKey($ids)->enabled()->notDone()->count() !== count($ids)) {
            throw new InvalidRouteStopMove('Alguna de las paradas ya no está pendiente.');
        }

        // A stop left behind when its customer was reassigned is not a cover: the new owner visits that customer.
        $leftBehind = RouteStop::query()
            ->whereKey($ids)
            ->whereNull('moved_from_route_id')
            ->whereDoesntHave('customer', fn ($customer) => $customer->where('driver_id', $source->assigned_driver_id))
            ->exists();

        if ($leftBehind) {
            throw new InvalidRouteStopMove('Alguna de las paradas es de un cliente que ya no es de este repartidor.');
        }

        return $stops;
    }

    /**
     * A customer is visited once a day: none of the moved stops may join a route where its customer already waits.
     *
     * @param  Collection<int, RouteStop>  $stops
     */
    private function ensureNoCustomerTwice(Route $destination, Collection $stops, Driver $driver): void
    {
        $waiting = $destination->stops()
            ->enabled()
            ->notDone()
            ->whereIn('customer_id', $stops->pluck('customer_id'))
            ->exists();

        if ($waiting) {
            $name = $driver->user?->name ?? 'el repartidor';

            throw new InvalidRouteStopMove("Algún cliente ya tiene una parada pendiente en la ruta de {$name}.");
        }
    }
}
