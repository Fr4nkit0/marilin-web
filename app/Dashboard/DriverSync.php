<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route as DeliveryRoute;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Each enabled driver's last contact from the app (any API call with their token), as of now.
 *
 * A driver is stale when their route of today is in progress and the app never reached the server or has not for longer than STALE_SYNC_MINUTES.
 */
class DriverSync
{
    /**
     * A driver on route whose app has not reached the server for longer than this is flagged.
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

        $lastUsed = PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $drivers->pluck('user_id'))
            ->groupBy('tokenable_id')
            ->selectRaw('tokenable_id, max(last_used_at) as last_used_at')
            ->pluck('last_used_at', 'tokenable_id');

        return $drivers
            ->map(function (Driver $driver) use ($lastUsed, $onRoute): array {
                $last = $lastUsed[$driver->user_id] ?? null;
                $lastAt = $last === null ? null : Carbon::parse($last);
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
