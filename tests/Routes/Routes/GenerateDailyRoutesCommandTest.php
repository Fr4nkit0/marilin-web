<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\VisitScheduler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const FRIDAY = 5;

beforeEach(function (): void {
    // 2026-10-23 is a Friday.
    $this->travelTo('2026-10-23 00:30:00');
    $this->driver = Driver::factory()->create(['enabled' => true]);
});

/**
 * A customer of the driver visited on the given weekday window, with its primary address.
 */
function visitedCustomer(Driver $driver, int $day = FRIDAY, ?string $windowStart = '09:00:00'): Customer
{
    $customer = Customer::factory()->create(['driver_id' => $driver->id]);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true]);
    CustomerPreference::factory()->create([
        'customer_id' => $customer->id,
        'day_of_week' => $day,
        'time_window_start' => $windowStart,
    ]);

    return $customer;
}

test('it creates a planned route for the day with one stop per customer visited that weekday', function (): void {
    $late = visitedCustomer($this->driver, windowStart: '15:00:00');
    $early = visitedCustomer($this->driver, windowStart: '08:30:00');

    $this->artisan('routes:generate')->assertSuccessful();

    $route = Route::query()->where('assigned_driver_id', $this->driver->id)->sole();
    $stops = RouteStop::query()->where('route_id', $route->id)->orderBy('scheduled_time')->get();

    expect($route->date->toDateString())->toBe('2026-10-23')
        ->and($route->status)->toBe('PLANNED')
        ->and($stops->pluck('customer_id')->all())->toBe([$early->id, $late->id])
        ->and($stops->pluck('scheduled_time')->all())->toBe(['08:30:00', '15:00:00'])
        ->and($stops->first()->address_id)->toBe($early->primaryAddress->id);
});

test('it leaves out customers that should not be visited that day', function (): void {
    $visited = visitedCustomer($this->driver);
    visitedCustomer($this->driver, day: FRIDAY - 1);
    Customer::query()->whereKey(visitedCustomer($this->driver)->id)->update(['enabled' => false]);
    CustomerPreference::query()->where('customer_id', visitedCustomer($this->driver)->id)->update(['enabled' => false]);
    CustomerAddress::query()->where('customer_id', visitedCustomer($this->driver)->id)->update(['enabled' => false]);
    visitedCustomer(Driver::factory()->create(['enabled' => false]));

    $this->artisan('routes:generate')->assertSuccessful();

    expect(RouteStop::query()->pluck('customer_id')->all())->toBe([$visited->id])
        ->and(Route::query()->count())->toBe(1);
});

test('it does not create a route for a driver with nobody to visit', function (): void {
    visitedCustomer($this->driver, day: FRIDAY + 1);

    $this->artisan('routes:generate')->assertSuccessful();

    expect(Route::query()->count())->toBe(0);
});

test('a customer with two windows on the same day gets one stop at the earliest one', function (): void {
    $customer = visitedCustomer($this->driver, windowStart: '14:00:00');
    CustomerPreference::factory()->create(['customer_id' => $customer->id, 'day_of_week' => FRIDAY, 'time_window_start' => '10:00:00']);

    $this->artisan('routes:generate')->assertSuccessful();

    expect(RouteStop::query()->pluck('scheduled_time')->all())->toBe(['10:00:00']);
});

test('running it again adds only the stops missing from the planned route, without duplicating any', function (): void {
    visitedCustomer($this->driver);
    $this->artisan('routes:generate')->assertSuccessful();
    $added = visitedCustomer($this->driver, windowStart: '11:00:00');

    $this->artisan('routes:generate')->assertSuccessful();

    expect(Route::query()->count())->toBe(1)
        ->and(RouteStop::query()->count())->toBe(2)
        ->and(RouteStop::query()->where('customer_id', $added->id)->sole()->scheduled_time)->toBe('11:00:00');
});

test('a same-day scheduled visit created before the generation no longer drops the preference stops', function (): void {
    $admin = User::factory()->superUser()->create();
    $hotel = Customer::factory()->create(['driver_id' => $this->driver->id, 'name' => 'Hotel Cerro San Bernardo']);
    CustomerAddress::factory()->create(['customer_id' => $hotel->id, 'is_primary' => true]);
    $calisaya = visitedCustomer($this->driver, windowStart: '12:30:00');
    $both = visitedCustomer($this->driver, windowStart: '10:00:00');
    $lateWindow = visitedCustomer($this->driver, windowStart: '16:00:00');

    $scheduler = app(VisitScheduler::class);
    $hotelVisit = $scheduler->schedule($hotel, today(), '09:00', 'Quiere 3 bidones de 20L', $admin);
    $bothVisit = $scheduler->schedule($both, today(), '15:00', null, $admin);
    $earlyVisit = $scheduler->schedule($lateWindow, today(), '11:00', null, $admin);

    $this->artisan('routes:generate')->assertSuccessful();

    $route = Route::query()->sole();
    $stops = RouteStop::query()->where('route_id', $route->id)->get()->keyBy('customer_id');

    expect($route->status)->toBe('PLANNED')
        ->and($stops)->toHaveCount(4)
        ->and($stops[$hotel->id]->scheduled_visit_id)->toBe($hotelVisit->id)
        ->and($stops[$calisaya->id]->scheduled_time)->toBe('12:30:00')
        ->and($stops[$calisaya->id]->address_id)->toBe($calisaya->primaryAddress->id)
        ->and($stops[$both->id]->scheduled_time)->toBe('10:00:00')
        ->and($stops[$both->id]->scheduled_visit_id)->toBe($bothVisit->id)
        ->and($stops[$lateWindow->id]->scheduled_time)->toBe('11:00:00')
        ->and($stops[$lateWindow->id]->scheduled_visit_id)->toBe($earlyVisit->id);
});

test('a disabled or done stop of the customer does not count as its stop on the planned route', function (): void {
    $customer = visitedCustomer($this->driver);
    $route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $customer->id, 'enabled' => false]);

    $this->artisan('routes:generate')->assertSuccessful();

    expect(RouteStop::query()->where('route_id', $route->id)->enabled()->where('customer_id', $customer->id)->count())->toBe(1);
});

test('a route that is not planned anymore is left untouched', function (string $status): void {
    $route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-23', 'status' => $status]);
    visitedCustomer($this->driver);

    $this->artisan('routes:generate')->assertSuccessful();

    expect(Route::query()->count())->toBe(1)
        ->and($route->fresh()->status)->toBe($status)
        ->and(RouteStop::query()->count())->toBe(0);
})->with(['IN_PROGRESS', 'COMPLETED', 'CANCELLED']);

test('it generates the routes of the date given as option', function (): void {
    visitedCustomer($this->driver, day: 1);

    $this->artisan('routes:generate', ['--date' => '2026-10-26'])->assertSuccessful();

    expect(Route::query()->sole()->date->toDateString())->toBe('2026-10-26');
});

test('it rejects an invalid date', function (): void {
    $this->artisan('routes:generate', ['--date' => 'tomorrow-ish'])->assertFailed();

    expect(Route::query()->count())->toBe(0);
});

test('the route generation is scheduled every night at 00:30', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, 'routes:generate'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 0 * * *');
});
