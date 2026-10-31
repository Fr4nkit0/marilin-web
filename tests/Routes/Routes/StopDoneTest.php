<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Enums\PaymentMethodCode;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Routes\Visits\Models\VisitOutcome;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
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

test('the driver page money leaves out cancelled sales and payments', function (): void {
    $stop = stopWithVisit('FINISHED');
    $visit = $stop->visit;
    Sale::factory()->create(['visit_id' => $visit->id, 'customer_id' => test()->customer->id, 'total' => 1000, 'status' => 'confirmed']);
    Sale::factory()->create(['visit_id' => $visit->id, 'customer_id' => test()->customer->id, 'total' => 400, 'status' => 'cancelled']);
    $cash = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Cash->value]);
    Payment::factory()->create(['visit_id' => $visit->id, 'customer_id' => test()->customer->id, 'payment_method_id' => $cash->id, 'amount' => 300, 'status' => 'CONFIRMED']);
    Payment::factory()->create(['visit_id' => $visit->id, 'customer_id' => test()->customer->id, 'payment_method_id' => $cash->id, 'amount' => 50, 'status' => 'CANCELLED']);

    $this->get(route('drivers.show', ['repartidor' => $this->driver->id, 'date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('route.totalSalesAmount', 1000)
            ->where('route.totalCollectedAmount', 300)
            ->where('route.stops.0.salesAmount', 1000)
            ->where('route.stops.0.paymentsAmount', 300));
});

test('the driver page totals count the sales and payments the app pushed without a visit, like the daily figures', function (): void {
    $visit = stopWithVisit('FINISHED')->visit;
    $cash = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Cash->value]);
    $transfer = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Transfer->value]);
    $driverUser = $this->driver->user_id;

    Sale::factory()->create(['visit_id' => $visit->id, 'customer_id' => $this->customer->id, 'total' => 1000, 'status' => 'confirmed', 'created_by' => $driverUser]);
    Sale::factory()->create(['visit_id' => null, 'customer_id' => $this->customer->id, 'total' => 1950, 'status' => 'confirmed', 'created_by' => $driverUser]);
    Sale::factory()->create(['visit_id' => null, 'customer_id' => $this->customer->id, 'total' => 700, 'status' => 'cancelled', 'created_by' => $driverUser]);
    Payment::factory()->create(['visit_id' => $visit->id, 'customer_id' => $this->customer->id, 'payment_method_id' => $cash->id, 'amount' => 300, 'received_by' => $driverUser]);
    Payment::factory()->create(['visit_id' => null, 'customer_id' => $this->customer->id, 'payment_method_id' => $transfer->id, 'amount' => 1950, 'received_by' => $driverUser]);
    Payment::factory()->create(['visit_id' => null, 'customer_id' => $this->customer->id, 'payment_method_id' => $cash->id, 'amount' => 90, 'status' => 'CANCELLED', 'received_by' => $driverUser]);

    $day = app(DailyFigures::class)->forDay('2026-10-20', DayScope::driver($this->driver));
    expect($day->totalSales)->toBe(2950.0)
        ->and($day->totalCollected)->toBe(2250.0);

    $this->get(route('drivers.show', ['repartidor' => $this->driver->id, 'date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('route.totalSalesAmount', 2950)
            ->where('route.totalCollectedAmount', 2250)
            ->where('route.stops.0.salesAmount', 1000)
            ->where('route.stops.0.paymentsAmount', 300));

    $this->get(route('sales.index', ['date' => '2026-10-20', 'repartidor_id' => $this->driver->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('metrics.totalSalesAmount', 2950));
});

test('dispatch tells a stop not visited yet from a visited one without news: the first is not done, neither carries an outcome', function (): void {
    $notVisited = stopWithVisit(null);
    $visited = stopWithVisit('FINISHED');

    $this->get(route('routes.index', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($notVisited, $visited): void {
            $stops = collect($page->toArray()['props']['routes'][0]['stops'])->keyBy('id');

            expect($stops[$notVisited->id])->toMatchArray(['done' => false, 'arrivedAt' => null, 'outcome' => null])
                ->and($stops[$visited->id]['done'])->toBeTrue()
                ->and($stops[$visited->id]['outcome'])->toBeNull();
        });
});
