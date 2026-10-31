<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\ScheduledVisits\VisitScheduler;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds each enabled driver's route for a date from the customers' visit preferences and the visits scheduled that date.
 * A driver's route that already exists that day (a same-day scheduled visit creates it) is completed with the missing
 * preference stops while it is still planned; a route already started, completed or cancelled is left untouched.
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
            ->with(['routes' => fn ($routes) => $routes->whereDate('date', $date)])
            ->each(function (Driver $driver) use ($date, &$created): void {
                $existing = $driver->routes->first();

                if ($existing !== null) {
                    if ($existing->status === RouteStatus::Planned->value) {
                        $this->complete($existing, $this->stopsFor($driver, $date->dayOfWeek));
                    }

                    return;
                }

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
     * Adds the preference stops the planned route is missing. A customer that already has an enabled, not done stop
     * keeps that one stop, at the earliest of its time and its preference's.
     *
     * @param  Collection<int, array{customer_id: int, address_id: int, scheduled_time: ?string}>  $stops
     */
    private function complete(Route $route, Collection $stops): void
    {
        DB::transaction(function () use ($route, $stops): void {
            $current = $route->stops()->enabled()->notDone()->orderBy('id')->get()->unique('customer_id')->keyBy('customer_id');

            $stops->each(function (array $stop) use ($route, $current): void {
                $existing = $current->get($stop['customer_id']);

                if ($existing === null) {
                    $route->stops()->create($stop);

                    return;
                }

                $earliest = collect([$existing->scheduled_time, $stop['scheduled_time']])->filter()->min();

                if ($earliest !== $existing->scheduled_time) {
                    $existing->update(['scheduled_time' => $earliest]);
                }
            });
        });
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
