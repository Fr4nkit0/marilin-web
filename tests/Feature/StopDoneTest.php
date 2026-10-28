<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Visit;
use App\Models\VisitOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 18:00:00');
    $this->actingAs(User::factory()->superUser()->create());
    $this->driver = Driver::factory()->create();
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_primary' => true]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20', 'status' => 'IN_PROGRESS']);
});

function stopWithVisit(?string $visitStatus, ?VisitOutcome $outcome = null): RouteStop
{
    $stop = RouteStop::factory()->create([
        'route_id' => test()->route->id,
        'customer_id' => test()->customer->id,
        'address_id' => test()->address->id,
    ]);

    if ($visitStatus !== null) {
        Visit::factory()->create([
            'route_stop_id' => $stop->id,
            'customer_id' => test()->customer->id,
            'driver_id' => test()->driver->id,
            'status' => $visitStatus,
            'outcome_id' => $outcome?->id,
        ]);
    }

    return $stop->fresh();
}

test('a stop with a finished visit is done whatever the visit outcome, absent included', function (): void {
    $absent = VisitOutcome::factory()->create(['name' => 'Ausente']);

    expect(stopWithVisit('FINISHED')->isDone())->toBeTrue()
        ->and(stopWithVisit('FINISHED', $absent)->isDone())->toBeTrue();
});

test('a stop without a finished visit is not done', function (): void {
    expect(stopWithVisit(null)->isDone())->toBeFalse()
        ->and(stopWithVisit('PENDING')->isDone())->toBeFalse()
        ->and(stopWithVisit('IN_PROGRESS')->isDone())->toBeFalse()
        ->and(stopWithVisit('CANCELLED')->isDone())->toBeFalse();
});

test('a rescheduled stop is not done even with a finished visit', function (): void {
    $original = stopWithVisit('FINISHED');
    RouteStop::factory()->create([
        'route_id' => $this->route->id,
        'customer_id' => $this->customer->id,
        'address_id' => $this->address->id,
        'is_rescheduled_from' => $original->id,
    ]);

    expect($original->fresh()->isDone())->toBeFalse()
        ->and($original->fresh()->effectiveStatus()->value)->toBe('RESCHEDULED');
});

function seedProgress(): void
{
    $absent = VisitOutcome::factory()->create(['name' => 'Ausente']);
    stopWithVisit('FINISHED');
    stopWithVisit('FINISHED', $absent);
    stopWithVisit('CANCELLED');
    stopWithVisit('IN_PROGRESS');
    $rescheduled = stopWithVisit('FINISHED');
    RouteStop::factory()->create([
        'route_id' => test()->route->id,
        'customer_id' => test()->customer->id,
        'address_id' => test()->address->id,
        'is_rescheduled_from' => $rescheduled->id,
    ]);
}

test('the routes page counts only done stops and sends the derived status of each stop', function (): void {
    seedProgress();

    $this->get(route('routes.index', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.0.stats.totalStops', 6)
            ->where('routes.0.stats.completedStops', 2)
            ->where('kpis.totalCompletedStops', 2)
            ->where('routes.0.stops', fn ($stops) => collect($stops)->pluck('status')->sort()->values()->all() === ['CANCELLED', 'FINISHED', 'FINISHED', 'IN_PROGRESS', 'PENDING', 'RESCHEDULED']
                && collect($stops)->where('done', true)->count() === 2));
});

test('the driver pages count only done stops and send the derived status of each stop', function (): void {
    seedProgress();

    $this->get(route('drivers.index', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('drivers.0.currentRoute.totalStops', 6)
            ->where('drivers.0.currentRoute.completedStops', 2));

    $this->get(route('drivers.show', ['repartidor' => $this->driver->id, 'date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('route.totalStops', 6)
            ->where('route.completedStops', 2)
            ->where('route.progressPercent', 33)
            ->where('route.stops', fn ($stops) => collect($stops)->pluck('status')->sort()->values()->all() === ['CANCELLED', 'FINISHED', 'FINISHED', 'IN_PROGRESS', 'PENDING', 'RESCHEDULED']
                && collect($stops)->where('done', true)->count() === 2));
});

test('the dashboard counts only done stops', function (): void {
    seedProgress();

    $this->get(route('dashboard', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.kpis.totalStops', 6)
            ->where('metrics.kpis.completedStops', 2)
            ->where('metrics.repartidores.0.completedStops', 2)
            ->where('metrics.details.stops', fn ($stops) => collect($stops)->pluck('status')->sort()->values()->all() === ['CANCELLED', 'FINISHED', 'FINISHED', 'IN_PROGRESS', 'PENDING', 'RESCHEDULED']));
});
