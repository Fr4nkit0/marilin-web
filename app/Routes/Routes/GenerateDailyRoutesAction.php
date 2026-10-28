<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Enums\RouteStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds each enabled driver's route for a date from the customers' visit preferences.
 * Drivers that already have a route that day are left untouched.
 */
final class GenerateDailyRoutesAction
{
    /**
     * @return int Number of routes created.
     */
    public function execute(CarbonInterface $date): int
    {
        $created = 0;

        Driver::query()
            ->enabled()
            ->whereDoesntHave('routes', fn ($routes) => $routes->whereDate('date', $date))
            ->each(function (Driver $driver) use ($date, &$created): void {
                $stops = $this->stopsFor($driver, $date->dayOfWeek);

                if ($stops->isEmpty()) {
                    return;
                }

                DB::transaction(function () use ($driver, $date, $stops): void {
                    $route = $driver->routes()->create([
                        'date' => $date->toDateString(),
                        'status' => RouteStatus::Planned->value,
                    ]);

                    $route->stops()->createMany($stops->all());
                });

                $created++;
            });

        return $created;
    }

    /**
     * One stop per customer visited that weekday, at the earliest of its windows.
     *
     * @return Collection<int, array{customer_id: int, address_id: int, scheduled_time: ?string}>
     */
    private function stopsFor(Driver $driver, int $dayOfWeek): Collection
    {
        $preferencesOfDay = fn ($preferences) => $preferences->enabled()->day($dayOfWeek);

        return $driver->customers()
            ->enabled()
            ->whereHas('preferences', $preferencesOfDay)
            ->with([
                'preferences' => $preferencesOfDay,
                'primaryAddress' => fn ($address) => $address->enabled(),
            ])
            ->get()
            ->filter(fn (Customer $customer): bool => $customer->primaryAddress !== null)
            ->map(fn (Customer $customer): array => [
                'customer_id' => $customer->id,
                'address_id' => $customer->primaryAddress->id,
                'scheduled_time' => $customer->preferences->pluck('time_window_start')->filter()->min(),
            ])
            ->values();
    }
}
