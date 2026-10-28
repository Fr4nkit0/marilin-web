<?php

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Visit;
use App\Models\VisitOutcome;
use App\Models\VisitStatusHistory;

test('route belongs to driver and filters by date and status scopes', function () {
    $driver = Driver::factory()->create();
    $today = now()->toDateString();

    $route = Route::factory()->inProgress()->create([
        'assigned_driver_id' => $driver->id,
        'date' => $today,
    ]);

    expect($route->driver->id)->toBe($driver->id);
    expect(Route::forDate($today)->count())->toBe(1);
    expect(Route::inProgress()->count())->toBe(1);
    expect(Route::planned()->count())->toBe(0);
});

test('route stop associates route, customer, address and supports rescheduling chain', function () {
    $route = Route::factory()->create();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $originalStop = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
    ]);

    $rescheduledStop = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
        'is_rescheduled_from' => $originalStop->id,
    ]);

    expect($rescheduledStop->rescheduledFrom->id)->toBe($originalStop->id);
    expect($originalStop->rescheduledTo->id)->toBe($rescheduledStop->id);
});

test('visit outcome triggers reschedule and links to visits', function () {
    $outcome = VisitOutcome::factory()->triggersReschedule()->create(['name' => 'Cliente Ausente']);

    expect($outcome->triggers_reschedule)->toBeTrue();
    expect(VisitOutcome::rescheduleTriggers()->count())->toBe(1);
});

test('visit tracks route stop, customer, driver, outcome, and status history', function () {
    $driver = Driver::factory()->create();
    $customer = Customer::factory()->create(['driver_id' => $driver->id]);
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $route = Route::factory()->create(['assigned_driver_id' => $driver->id]);
    $stop = RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
    ]);
    $outcome = VisitOutcome::factory()->create();
    $user = User::factory()->create();

    $visit = Visit::factory()->finished()->create([
        'route_stop_id' => $stop->id,
        'customer_id' => $customer->id,
        'driver_id' => $driver->id,
        'outcome_id' => $outcome->id,
        'created_by' => $user->id,
    ]);

    $history = VisitStatusHistory::factory()->create([
        'visit_id' => $visit->id,
        'status' => 'FINISHED',
        'outcome_id' => $outcome->id,
        'created_by' => $user->id,
    ]);

    expect($visit->routeStop->id)->toBe($stop->id);
    expect($visit->customer->id)->toBe($customer->id);
    expect($visit->driver->id)->toBe($driver->id);
    expect($visit->outcome->id)->toBe($outcome->id);
    expect($visit->creator->id)->toBe($user->id);
    expect($visit->statusHistories)->toHaveCount(1);
    expect($visit->statusHistories->first()->id)->toBe($history->id);
    expect(Visit::finished()->count())->toBe(1);
});
