<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
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

test('running it again does not duplicate routes or touch an existing one', function (): void {
    visitedCustomer($this->driver);
    $this->artisan('routes:generate')->assertSuccessful();
    visitedCustomer($this->driver);

    $this->artisan('routes:generate')->assertSuccessful();

    expect(Route::query()->count())->toBe(1)
        ->and(RouteStop::query()->count())->toBe(1);
});

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
