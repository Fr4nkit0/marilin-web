<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\ScheduledVisits\VisitScheduler;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds each enabled driver's route for a date from the customers' visit preferences and the visits scheduled that date.
 * Drivers that already have a route that day are left untouched.
 */
final class GenerateDailyRoutesAction
{
    public function __construct(private readonly VisitScheduler $scheduler) {}

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
                $scheduledVisits = $this->scheduledVisitsFor($driver, $date);

                if ($stops->isEmpty() && $scheduledVisits->isEmpty()) {
                    return;
                }

                DB::transaction(function () use ($driver, $date, $stops, $scheduledVisits): void {
                    $route = $driver->routes()->create([
                        'date' => $date->toDateString(),
                        'status' => RouteStatus::Planned->value,
                    ]);

                    $route->stops()->createMany($stops->all());

                    // After the preference stops, so a customer visited that weekday keeps one stop that carries the visit.
                    $scheduledVisits->each(fn (ScheduledVisit $visit) => $this->scheduler->attach($visit, $route));
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

    /**
     * The visits scheduled that date for the driver's enabled customers with a primary address, not on a route yet.
     *
     * @return Collection<int, ScheduledVisit>
     */
    private function scheduledVisitsFor(Driver $driver, CarbonInterface $date): Collection
    {
        return ScheduledVisit::query()
            ->scheduled()
            ->whereDate('date', $date->toDateString())
            ->whereNull('route_stop_id')
            ->whereHas('customer', fn ($customer) => $customer
                ->where('driver_id', $driver->id)
                ->enabled()
                ->whereHas('primaryAddress', fn ($address) => $address->enabled()))
            ->with('customer')
            ->orderBy('id')
            ->get();
    }
}
