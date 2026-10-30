<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\Enums\ScheduledVisitDisplayStatus;
use App\Routes\ScheduledVisits\InvalidScheduledVisit;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\ScheduledVisits\VisitScheduler;
use App\Routes\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const WEDNESDAY_TODAY = '2026-10-21';
const NEXT_FRIDAY = '2026-10-23';

beforeEach(function (): void {
    // 2026-10-21 is a Wednesday.
    $this->travelTo(WEDNESDAY_TODAY.' 10:00:00');
    $this->driverUser = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->admin = User::factory()->create();
    $this->scheduler = app(VisitScheduler::class);
});

/**
 * A customer of the driver with its primary address and, optionally, a visit preference.
 */
function schedulableCustomer(?Driver $driver, ?int $preferenceDay = null, ?string $windowStart = null): Customer
{
    $customer = Customer::factory()->create(['driver_id' => $driver?->id]);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true]);

    if ($preferenceDay !== null) {
        CustomerPreference::factory()->create([
            'customer_id' => $customer->id,
            'day_of_week' => $preferenceDay,
            'time_window_start' => $windowStart,
        ]);
    }

    return $customer;
}

function todayRoute(Driver $driver, string $date = WEDNESDAY_TODAY): Route
{
    return Route::factory()->create(['assigned_driver_id' => $driver->id, 'date' => $date, 'status' => 'PLANNED']);
}

test('a visit for a future date without route waits, and routes:generate turns it into a stop on that date', function (): void {
    $customer = schedulableCustomer($this->driver);

    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(NEXT_FRIDAY), '11:00', 'Llamó, quiere 2 bidones', $this->admin);

    expect($visit->status)->toBe('SCHEDULED')
        ->and($visit->route_stop_id)->toBeNull()
        ->and($visit->created_by)->toBe($this->admin->id)
        ->and(RouteStop::query()->count())->toBe(0)
        ->and($visit->displayStatus())->toBe(ScheduledVisitDisplayStatus::Scheduled);

    $this->artisan('routes:generate', ['--date' => NEXT_FRIDAY])->assertSuccessful();

    $route = Route::query()->where('assigned_driver_id', $this->driver->id)->sole();
    $stop = RouteStop::query()->sole();
    $visit->refresh();

    expect($route->date->toDateString())->toBe(NEXT_FRIDAY)
        ->and($route->status)->toBe('PLANNED')
        ->and($stop->route_id)->toBe($route->id)
        ->and($stop->customer_id)->toBe($customer->id)
        ->and($stop->address_id)->toBe($customer->primaryAddress->id)
        ->and($stop->scheduled_time)->toBe('11:00:00')
        ->and($stop->note)->toBe('Llamó, quiere 2 bidones')
        ->and($stop->scheduled_visit_id)->toBe($visit->id)
        ->and($stop->isRequested())->toBeTrue()
        ->and($visit->route_stop_id)->toBe($stop->id)
        ->and($visit->created_stop)->toBeTrue()
        ->and($visit->displayStatus())->toBe(ScheduledVisitDisplayStatus::OnRoute);
});

test('routes:generate merges a scheduled visit with the preference stop of that date', function (): void {
    $customer = schedulableCustomer($this->driver, preferenceDay: 5, windowStart: '09:00:00');
    $other = schedulableCustomer($this->driver, preferenceDay: 5, windowStart: '15:00:00');
    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(NEXT_FRIDAY), '12:00', 'Pasar igual', $this->admin);

    $this->artisan('routes:generate', ['--date' => NEXT_FRIDAY])->assertSuccessful();

    $stops = RouteStop::query()->orderBy('scheduled_time')->get();
    $visit->refresh();

    expect($stops)->toHaveCount(2)
        ->and($stops->pluck('customer_id')->all())->toBe([$customer->id, $other->id])
        ->and($stops[0]->scheduled_time)->toBe('09:00:00')
        ->and($stops[0]->note)->toBe('Pasar igual')
        ->and($stops[0]->scheduled_visit_id)->toBe($visit->id)
        ->and($stops[1]->scheduled_visit_id)->toBeNull()
        ->and($visit->route_stop_id)->toBe($stops[0]->id)
        ->and($visit->created_stop)->toBeFalse();
});

test('routes:generate leaves out scheduled visits of disabled customers and cancelled visits', function (): void {
    $disabled = schedulableCustomer($this->driver);
    $this->scheduler->schedule($disabled, CarbonImmutable::parse(NEXT_FRIDAY), null, null, $this->admin);
    $disabled->update(['enabled' => false]);
    $cancelled = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(NEXT_FRIDAY), null, null, $this->admin);
    $this->scheduler->cancel($cancelled, $this->admin);

    $this->artisan('routes:generate', ['--date' => NEXT_FRIDAY])->assertSuccessful();

    expect(Route::query()->count())->toBe(0)
        ->and(RouteStop::query()->count())->toBe(0);
});

test('a visit for today with an existing route adds the stop at once and bootstrap marks it as requested', function (): void {
    $route = todayRoute($this->driver);
    $customer = schedulableCustomer($this->driver);

    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(WEDNESDAY_TODAY), null, 'Llamó', $this->admin);

    $stop = RouteStop::query()->sole();
    expect($stop->route_id)->toBe($route->id)
        ->and($stop->scheduled_visit_id)->toBe($visit->id)
        ->and($visit->route_stop_id)->toBe($stop->id);

    Sanctum::actingAs($this->driverUser, ['*']);
    $payload = collect($this->getJson('/api/v1/sync/bootstrap')->assertOk()->json('data.stops'))->firstWhere('id', $stop->id);

    expect($payload['is_requested'])->toBeTrue()
        ->and($payload['note'])->toBe('Llamó');
});

test('a visit for a future date whose route already exists is added at once', function (): void {
    $route = todayRoute($this->driver, NEXT_FRIDAY);

    $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(NEXT_FRIDAY), null, null, $this->admin);

    expect(RouteStop::query()->sole()->route_id)->toBe($route->id);
});

test('a visit for today without a route creates the planned route with the stop', function (): void {
    $customer = schedulableCustomer($this->driver);

    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(WEDNESDAY_TODAY), '16:30', null, $this->admin);

    $route = Route::query()->sole();
    expect($route->assigned_driver_id)->toBe($this->driver->id)
        ->and($route->date->toDateString())->toBe(WEDNESDAY_TODAY)
        ->and($route->status)->toBe('PLANNED')
        ->and(RouteStop::query()->sole()->scheduled_visit_id)->toBe($visit->id)
        ->and(RouteStop::query()->sole()->scheduled_time)->toBe('16:30:00');
});

test('a customer with a stop that day keeps one stop carrying the visit at the earliest time', function (): void {
    $route = todayRoute($this->driver);
    $customer = schedulableCustomer($this->driver);
    $existing = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $customer->primaryAddress->id,
        'scheduled_time' => '14:00:00',
    ]);

    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(WEDNESDAY_TODAY), '09:30', 'Temprano', $this->admin);

    $existing->refresh();
    expect(RouteStop::query()->count())->toBe(1)
        ->and($existing->scheduled_visit_id)->toBe($visit->id)
        ->and($existing->note)->toBe('Temprano')
        ->and($existing->scheduled_time)->toBe('09:30:00')
        ->and($visit->created_stop)->toBeFalse();
});

test('the earliest time keeps the stop time when the visit has none or a later one', function (?string $visitTime): void {
    $route = todayRoute($this->driver);
    $customer = schedulableCustomer($this->driver);
    $existing = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $customer->primaryAddress->id,
        'scheduled_time' => '10:00:00',
    ]);

    $this->scheduler->schedule($customer, CarbonImmutable::parse(WEDNESDAY_TODAY), $visitTime, null, $this->admin);

    expect($existing->refresh()->scheduled_time)->toBe('10:00:00');
})->with([null, '18:00']);

test('scheduling is rejected with a message for the web and stores nothing', function (Closure $customer, string $date, string $message): void {
    $target = $customer($this->driver);

    expect(fn () => $this->scheduler->schedule($target, CarbonImmutable::parse($date), null, null, $this->admin))
        ->toThrow(InvalidScheduledVisit::class, $message);

    expect(ScheduledVisit::query()->count())->toBe(0)
        ->and(Route::query()->count())->toBe(0)
        ->and(RouteStop::query()->count())->toBe(0);
})->with([
    'customer without driver' => [fn () => schedulableCustomer(null), WEDNESDAY_TODAY, 'Asigná un repartidor antes de agendar.'],
    'past date' => [fn (Driver $driver) => schedulableCustomer($driver), '2026-10-20', 'La fecha no puede ser anterior a hoy.'],
    'disabled customer' => [fn (Driver $driver) => tap(schedulableCustomer($driver))->update(['enabled' => false]), WEDNESDAY_TODAY, 'El cliente está deshabilitado.'],
]);

test('cancelling a visit disables the stop created only for it and pull sends the change', function (): void {
    $customer = schedulableCustomer($this->driver);
    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(WEDNESDAY_TODAY), null, 'Llamó', $this->admin);
    $stop = RouteStop::query()->sole();
    $cursor = now()->toIso8601String();
    $this->travel(5)->minutes();

    $this->scheduler->cancel($visit, $this->admin);

    $visit->refresh();
    expect($visit->status)->toBe('CANCELLED')
        ->and($visit->cancelled_by)->toBe($this->admin->id)
        ->and($visit->cancelled_at)->not->toBeNull()
        ->and($visit->displayStatus())->toBe(ScheduledVisitDisplayStatus::Cancelled)
        ->and($stop->refresh()->enabled)->toBeFalse();

    Sanctum::actingAs($this->driverUser, ['*']);
    $pulled = collect($this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.route_stops'))->firstWhere('id', $stop->id);

    expect($pulled)->not->toBeNull()
        ->and($pulled['enabled'])->toBeFalse();
});

test('cancelling a visit carried by a preference stop keeps the stop without the mark and note', function (): void {
    $route = todayRoute($this->driver);
    $customer = schedulableCustomer($this->driver);
    $stop = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $customer->id, 'address_id' => $customer->primaryAddress->id]);
    $visit = $this->scheduler->schedule($customer, CarbonImmutable::parse(WEDNESDAY_TODAY), null, 'Llamó', $this->admin);
    $cursor = now()->toIso8601String();
    $this->travel(5)->minutes();

    $this->scheduler->cancel($visit, $this->admin);

    $stop->refresh();
    expect($stop->enabled)->toBeTrue()
        ->and($stop->scheduled_visit_id)->toBeNull()
        ->and($stop->note)->toBeNull()
        ->and($stop->isRequested())->toBeFalse();

    Sanctum::actingAs($this->driverUser, ['*']);
    $pulled = collect($this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.route_stops'))->firstWhere('id', $stop->id);

    expect($pulled['enabled'])->toBeTrue()
        ->and($pulled['is_requested'])->toBeFalse()
        ->and($pulled['note'])->toBeNull()
        ->and($pulled['scheduled_visit_id'])->toBeNull();
});

test('a visit already done or already cancelled cannot be cancelled', function (): void {
    $done = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(WEDNESDAY_TODAY), null, null, $this->admin);
    Visit::factory()->finished()->create(['route_stop_id' => $done->route_stop_id, 'customer_id' => $done->customer_id]);

    expect(fn () => $this->scheduler->cancel($done, $this->admin))->toThrow(InvalidScheduledVisit::class, 'La visita ya se hizo.')
        ->and($done->refresh()->status)->toBe('SCHEDULED')
        ->and(RouteStop::query()->sole()->enabled)->toBeTrue();

    $cancelled = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(NEXT_FRIDAY), null, null, $this->admin);
    $this->scheduler->cancel($cancelled, $this->admin);

    expect(fn () => $this->scheduler->cancel($cancelled, $this->admin))->toThrow(InvalidScheduledVisit::class, 'La visita ya está cancelada.');
});

test('pull sends the requested mark, the note and the scheduled visit of a stop', function (): void {
    $visit = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(WEDNESDAY_TODAY), null, 'Timbre roto', $this->admin);

    Sanctum::actingAs($this->driverUser, ['*']);
    $pulled = $this->getJson('/api/v1/sync/pull')->assertOk()->json('data.route_stops.0');

    expect($pulled['is_requested'])->toBeTrue()
        ->and($pulled['note'])->toBe('Timbre roto')
        ->and($pulled['scheduled_visit_id'])->toBe($visit->id);
});

test('the display status follows the stop: visited, and not visited once its day passed', function (): void {
    $visited = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(WEDNESDAY_TODAY), null, null, $this->admin);
    $missed = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(WEDNESDAY_TODAY), null, null, $this->admin);
    Visit::factory()->finished()->create(['route_stop_id' => $visited->route_stop_id, 'customer_id' => $visited->customer_id]);

    expect($visited->refresh()->displayStatus())->toBe(ScheduledVisitDisplayStatus::Visited)
        ->and($missed->refresh()->displayStatus())->toBe(ScheduledVisitDisplayStatus::OnRoute);

    $this->travelTo('2026-10-22 08:00:00');

    expect($missed->refresh()->displayStatus())->toBe(ScheduledVisitDisplayStatus::NotVisited)
        ->and(ScheduledVisitDisplayStatus::NotVisited->label())->toBe('Sin visitar');
});

test('a requested stop left unvisited goes to the review inbox like any planned stop', function (): void {
    $visit = $this->scheduler->schedule(schedulableCustomer($this->driver), CarbonImmutable::parse(WEDNESDAY_TODAY), null, null, $this->admin);
    $this->travelTo('2026-10-22 01:00:00');

    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect(RouteStop::query()->findOrFail($visit->route_stop_id)->needs_review)->toBeTrue();
});
