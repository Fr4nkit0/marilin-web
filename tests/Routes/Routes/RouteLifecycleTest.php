<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\VisitScheduler;
use App\Routes\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $this->driver = Driver::factory()->create(['enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_primary' => true]);
});

function lifecycleRoute(string $date, string $status, array $attributes = []): Route
{
    return Route::factory()->create([
        'assigned_driver_id' => Driver::factory()->create()->id,
        'date' => $date,
        'status' => $status,
        ...$attributes,
    ]);
}

function lifecycleStop(Route $route, ?string $visitedAt = null): RouteStop
{
    $stop = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => test()->customer->id,
        'address_id' => test()->address->id,
    ]);

    if ($visitedAt !== null) {
        Visit::factory()->create([
            'route_stop_id' => $stop->id,
            'customer_id' => test()->customer->id,
            'driver_id' => $route->assigned_driver_id,
            'status' => 'FINISHED',
            'arrived_at' => $visitedAt,
        ]);
    }

    return $stop;
}

test('S3: a scheduled visit attached to a completed route of today reopens it', function (): void {
    $route = lifecycleRoute('2026-10-22', 'COMPLETED', [
        'assigned_driver_id' => $this->driver->id,
        'started_at' => '2026-10-22 08:00:00',
        'completed_at' => '2026-10-22 11:00:00',
    ]);
    lifecycleStop($route, '2026-10-22 11:00:00');

    app(VisitScheduler::class)->schedule($this->customer, CarbonImmutable::parse('2026-10-22'), '15:00', null, User::factory()->create());

    $route->refresh();

    expect($route->stops()->count())->toBe(2)
        ->and($route->status)->toBe('IN_PROGRESS')
        ->and($route->started_at->format('Y-m-d H:i:s'))->toBe('2026-10-22 08:00:00')
        ->and($route->completed_at)->toBeNull();
});

test('S3: a scheduled visit attached to a planned route leaves it planned', function (): void {
    $route = lifecycleRoute('2026-10-22', 'PLANNED', ['assigned_driver_id' => $this->driver->id]);

    app(VisitScheduler::class)->schedule($this->customer, CarbonImmutable::parse('2026-10-22'), '15:00', null, User::factory()->create());

    expect($route->fresh()->status)->toBe('PLANNED')
        ->and($route->fresh()->started_at)->toBeNull();
});

test('S5: routes:close-past closes the past routes still planned or in progress, with their latest arrival', function (): void {
    $planned = lifecycleRoute('2026-10-21', 'PLANNED');
    lifecycleStop($planned);
    $inProgress = lifecycleRoute('2026-10-20', 'IN_PROGRESS', ['started_at' => '2026-10-20 08:00:00']);
    lifecycleStop($inProgress, '2026-10-20 09:30:00');
    lifecycleStop($inProgress, '2026-10-20 10:45:00');
    lifecycleStop($inProgress);

    $this->artisan('routes:close-past')->expectsOutput('Closed 2 past route(s).')->assertSuccessful();

    $planned->refresh();
    $inProgress->refresh();

    expect($planned->status)->toBe('COMPLETED')
        ->and($planned->completed_at)->toBeNull()
        ->and($inProgress->status)->toBe('COMPLETED')
        ->and($inProgress->started_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 08:00:00')
        ->and($inProgress->completed_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 10:45:00');
});

test("S5: routes:close-past leaves today's routes and cancelled routes alone", function (): void {
    $today = lifecycleRoute('2026-10-22', 'IN_PROGRESS', ['started_at' => '2026-10-22 08:00:00']);
    lifecycleStop($today, '2026-10-22 08:00:00');
    lifecycleStop($today);
    $cancelled = lifecycleRoute('2026-10-21', 'CANCELLED');
    lifecycleStop($cancelled);

    $this->artisan('routes:close-past')->expectsOutput('Closed 0 past route(s).')->assertSuccessful();

    expect($today->fresh()->status)->toBe('IN_PROGRESS')
        ->and($today->fresh()->completed_at)->toBeNull()
        ->and($cancelled->fresh()->status)->toBe('CANCELLED')
        ->and($cancelled->fresh()->completed_at)->toBeNull();
});

test('S5: the not done stops of a closed route still go to the review inbox', function (): void {
    $route = lifecycleRoute('2026-10-21', 'IN_PROGRESS', ['started_at' => '2026-10-21 08:00:00']);
    $done = lifecycleStop($route, '2026-10-21 09:00:00');
    $notDone = lifecycleStop($route);

    $this->artisan('routes:close-past')->assertSuccessful();
    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect($route->fresh()->status)->toBe('COMPLETED')
        ->and($notDone->fresh()->needs_review)->toBeTrue()
        ->and($done->fresh()->needs_review)->toBeFalse();
});

test('S5: routes:close-past runs every night at 01:00', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'routes:close-past'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 1 * * *');
});
