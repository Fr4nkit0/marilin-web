<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Accounts\Models\Driver;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Each enabled driver's last successful sync from the app (bootstrap, pull or push), as of now.
 *
 * A driver is stale when their route of today is in progress and the app never synced or has not for longer than STALE_SYNC_MINUTES.
 */
class DriverSync
{
    /**
     * A driver on route whose app has not synced for longer than this is flagged.
     */
    public const int STALE_SYNC_MINUTES = 30;

    /**
     * Every enabled driver, by name.
     *
     * @return array<int, array{id: int, name: string, lastSyncAt: string|null, onRoute: bool, stale: bool}>
     */
    public function all(): array
    {
        return $this->statuses(Driver::query(), $this->onRouteDriverIds());
    }

    /**
     * Only the stale drivers, by name; a day with nobody on route costs a single query.
     *
     * @return array<int, array{id: int, name: string, lastSyncAt: string|null, onRoute: bool, stale: bool}>
     */
    public function stale(): array
    {
        $onRoute = $this->onRouteDriverIds();

        if ($onRoute->isEmpty()) {
            return [];
        }

        $statuses = $this->statuses(Driver::query()->whereIn('id', $onRoute->keys()), $onRoute);

        return array_values(array_filter($statuses, fn (array $status): bool => $status['stale']));
    }

    /**
     * @param  Builder<Driver>  $drivers
     * @param  Collection<int, int>  $onRoute  driver ids as keys
     * @return array<int, array{id: int, name: string, lastSyncAt: string|null, onRoute: bool, stale: bool}>
     */
    private function statuses(Builder $drivers, Collection $onRoute): array
    {
        $drivers = $drivers->with('user:id,name')->where('enabled', true)->get();

        return $drivers
            ->map(function (Driver $driver) use ($onRoute): array {
                $lastAt = $driver->last_synced_at;
                $isOnRoute = $onRoute->has($driver->id);

                return [
                    'id' => $driver->id,
                    'name' => $driver->user?->name ?? 'Repartidor',
                    'lastSyncAt' => $lastAt?->toIso8601String(),
                    'onRoute' => $isOnRoute,
                    'stale' => $isOnRoute && ($lastAt === null || $lastAt->lt(now()->subMinutes(self::STALE_SYNC_MINUTES))),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, int> driver ids as keys
     */
    private function onRouteDriverIds(): Collection
    {
        return DeliveryRoute::query()
            ->whereDate('date', Carbon::today()->toDateString())
            ->where('status', RouteStatus::InProgress->value)
            ->pluck('assigned_driver_id')
            ->flip();
    }
}
