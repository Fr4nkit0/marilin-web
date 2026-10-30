<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\ScheduledVisits\VisitScheduler;
use App\Routes\Visits\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    // 2026-10-21 is a Wednesday.
    $this->travelTo('2026-10-21 10:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->actingAs($this->admin);
    $this->driver = Driver::factory()->create(['user_id' => User::factory()->create(['enabled' => true])->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_primary' => true]);
});

function webScheduledVisit(string $date, ?string $time = null, ?string $note = null): ScheduledVisit
{
    return app(VisitScheduler::class)->schedule(test()->customer, CarbonImmutable::parse($date), $time, $note, test()->admin);
}

test('the admin schedules a visit for a future date: it is stored and waits for the route', function (): void {
    $this->post(route('scheduled-visits.store', $this->customer), ['date' => '2026-10-23', 'scheduled_time' => '11:00', 'note' => 'Llamó, quiere 2 bidones'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHas('success', 'Visita agendada.');

    $visit = ScheduledVisit::query()->sole();
    expect($visit->customer_id)->toBe($this->customer->id)
        ->and($visit->date->toDateString())->toBe('2026-10-23')
        ->and($visit->scheduled_time)->toBe('11:00:00')
        ->and($visit->note)->toBe('Llamó, quiere 2 bidones')
        ->and($visit->status)->toBe('SCHEDULED')
        ->and($visit->created_by)->toBe($this->admin->id)
        ->and(RouteStop::query()->count())->toBe(0);
});

test('a visit scheduled for today becomes a stop on the driver route at once', function (): void {
    $this->post(route('scheduled-visits.store', $this->customer), ['date' => '2026-10-21'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Visita agendada.');

    $stop = RouteStop::query()->sole();
    expect($stop->customer_id)->toBe($this->customer->id)
        ->and($stop->route->assigned_driver_id)->toBe($this->driver->id)
        ->and($stop->route->date->toDateString())->toBe('2026-10-21')
        ->and($stop->scheduled_visit_id)->toBe(ScheduledVisit::query()->sole()->id);
});

test('a customer without a driver cannot be scheduled and nothing is saved', function (): void {
    $this->customer->update(['driver_id' => null]);

    $this->from(route('customers.show', $this->customer))
        ->post(route('scheduled-visits.store', $this->customer), ['date' => '2026-10-23'])
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHasErrors(['customer' => 'Asigná un repartidor antes de agendar.'])
        ->assertSessionMissing('success');

    expect(ScheduledVisit::query()->count())->toBe(0);
});

test('a past date is rejected in Spanish next to the date and nothing is saved', function (): void {
    $this->post(route('scheduled-visits.store', $this->customer), ['date' => '2026-10-20'])
        ->assertSessionHasErrors(['date' => 'La fecha no puede ser anterior a hoy.']);

    expect(ScheduledVisit::query()->count())->toBe(0)
        ->and(RouteStop::query()->count())->toBe(0);
});

test('the date is required and the time and note keep their formats', function (): void {
    $this->post(route('scheduled-visits.store', $this->customer), ['date' => '', 'scheduled_time' => '25:99', 'note' => str_repeat('a', 501)])
        ->assertSessionHasErrors(['date', 'scheduled_time', 'note']);

    $this->post(route('scheduled-visits.store', $this->customer), ['date' => '23/10/2026'])
        ->assertSessionHasErrors(['date']);

    expect(ScheduledVisit::query()->count())->toBe(0);
});

test('the admin cancels a visit before it is visited and the stop created for it is disabled', function (): void {
    $visit = webScheduledVisit('2026-10-21', note: 'Pasar a la tarde');

    $this->patch(route('scheduled-visits.cancel', $visit))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHas('success', 'Visita cancelada.');

    $visit->refresh();
    expect($visit->status)->toBe('CANCELLED')
        ->and($visit->cancelled_by)->toBe($this->admin->id)
        ->and($visit->routeStop->enabled)->toBeFalse();
});

test('a visit already done cannot be cancelled', function (): void {
    $visit = webScheduledVisit('2026-10-21');
    Visit::factory()->create([
        'route_stop_id' => $visit->route_stop_id,
        'customer_id' => $this->customer->id,
        'driver_id' => $this->driver->id,
        'status' => 'FINISHED',
    ]);

    $this->from(route('customers.show', $this->customer))
        ->patch(route('scheduled-visits.cancel', $visit))
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHasErrors(['scheduled_visit' => 'La visita ya se hizo.'])
        ->assertSessionMissing('success');

    expect($visit->refresh()->status)->toBe('SCHEDULED');
});

test('the customer page lists the scheduled visits newest date first with their status label and whether they can be cancelled', function (): void {
    $upcoming = webScheduledVisit('2026-10-28', '09:30', 'Llamó');
    $today = webScheduledVisit('2026-10-21');
    $cancelled = webScheduledVisit('2026-10-24');
    $cancelled->update(['status' => 'CANCELLED']);

    // Yesterday's visit rode on a stop that was not visited.
    $yesterdayRoute = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20', 'status' => 'COMPLETED']);
    $missed = ScheduledVisit::factory()->create(['customer_id' => $this->customer->id, 'date' => '2026-10-20', 'created_by' => $this->admin->id]);
    app(VisitScheduler::class)->attach($missed, $yesterdayRoute);

    $this->get(route('customers.show', $this->customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Customers/Show')
            ->where('canSchedule', true)
            ->has('scheduledVisits', 4)
            ->where('scheduledVisits.0', [
                'id' => $upcoming->id,
                'date' => '2026-10-28',
                'scheduledTime' => '09:30',
                'note' => 'Llamó',
                'status' => 'SCHEDULED',
                'statusLabel' => 'Agendada',
                'canCancel' => true,
            ])
            ->where('scheduledVisits.1.id', $cancelled->id)
            ->where('scheduledVisits.1.statusLabel', 'Cancelada')
            ->where('scheduledVisits.1.canCancel', false)
            ->where('scheduledVisits.2.id', $today->id)
            ->where('scheduledVisits.2.statusLabel', 'En ruta')
            ->where('scheduledVisits.2.canCancel', true)
            ->where('scheduledVisits.3.id', $missed->id)
            ->where('scheduledVisits.3.status', 'NOT_VISITED')
            ->where('scheduledVisits.3.statusLabel', 'Sin visitar')
            ->where('scheduledVisits.3.canCancel', false));
});

test('the customer page keeps every upcoming visit and only the last twenty past ones', function (): void {
    foreach (range(1, 22) as $daysAgo) {
        ScheduledVisit::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => today()->subDays($daysAgo)->toDateString(),
            'created_by' => $this->admin->id,
        ]);
    }
    ScheduledVisit::factory()->count(3)->create(['customer_id' => $this->customer->id, 'date' => '2026-11-05', 'created_by' => $this->admin->id]);

    $this->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page
            ->has('scheduledVisits', 23)
            ->where('scheduledVisits.0.date', '2026-11-05')
            ->where('scheduledVisits.22.date', '2026-10-01'));
});

test('a visited scheduled visit reads Visitada', function (): void {
    $visit = webScheduledVisit('2026-10-21');
    Visit::factory()->create([
        'route_stop_id' => $visit->route_stop_id,
        'customer_id' => $this->customer->id,
        'driver_id' => $this->driver->id,
        'status' => 'FINISHED',
    ]);

    $this->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scheduledVisits.0.statusLabel', 'Visitada')
            ->where('scheduledVisits.0.canCancel', false));
});

test('a customer without a driver cannot be scheduled from the page', function (): void {
    $this->customer->update(['driver_id' => null]);

    $this->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canSchedule', false)
            ->where('scheduledVisits', []));
});

test('the routes page and the driver page mark a requested stop and show its note', function (): void {
    $visit = webScheduledVisit('2026-10-21', note: 'Quiere 2 bidones');
    $plain = RouteStop::factory()->create([
        'route_id' => $visit->routeStop->route_id,
        'customer_id' => Customer::factory()->create(['driver_id' => $this->driver->id])->id,
    ]);

    $stops = fn ($stops) => collect($stops)->keyBy('id')->map(fn ($stop) => [$stop['is_requested'], $stop['note']])->all() === [
        $visit->route_stop_id => [true, 'Quiere 2 bidones'],
        $plain->id => [false, null],
    ];

    $this->get(route('routes.index', ['date' => '2026-10-21']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('routes.0.stops', $stops));

    $this->get(route('drivers.show', ['repartidor' => $this->driver->id, 'date' => '2026-10-21']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('route.stops', $stops));
});

test('a cancelled scheduled visit stop is neither listed nor counted in Despacho, the driver pages and the dashboard', function (): void {
    $cancelled = webScheduledVisit('2026-10-21', note: 'Ya no hace falta');
    app(VisitScheduler::class)->cancel($cancelled, $this->admin);
    $routeId = $cancelled->routeStop->route_id;
    $enabled = RouteStop::factory()->count(2)->create([
        'route_id' => $routeId,
        'customer_id' => Customer::factory()->create(['driver_id' => $this->driver->id])->id,
    ]);
    Visit::factory()->finished()->create([
        'route_stop_id' => $enabled[0]->id,
        'customer_id' => $enabled[0]->customer_id,
        'driver_id' => $this->driver->id,
    ]);
    $enabledIds = $enabled->pluck('id')->sort()->values()->all();
    $listed = fn ($stops) => collect($stops)->pluck('id')->sort()->values()->all() === $enabledIds;

    $this->get(route('routes.index', ['date' => '2026-10-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.0.stops', $listed)
            ->where('routes.0.stats.totalStops', 2)
            ->where('routes.0.stats.completedStops', 1)
            ->where('kpis.totalPlannedStops', 2));

    $this->get(route('drivers.show', ['repartidor' => $this->driver->id, 'date' => '2026-10-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('route.stops', $listed)
            ->where('route.totalStops', 2)
            ->where('route.completedStops', 1)
            ->where('route.progressPercent', 50));

    $this->get(route('drivers.index', ['date' => '2026-10-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('drivers.0.currentRoute.totalStops', 2)
            ->where('drivers.0.currentRoute.completedStops', 1));

    $this->get(route('dashboard', ['date' => '2026-10-21']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.kpis.totalStops', 2)
            ->where('metrics.kpis.completedStops', 1)
            ->where('metrics.repartidores.0.totalStops', 2)
            ->where('metrics.details.stops', $listed));
});

test('the stop of a cancelled scheduled visit is never flagged as unvisited', function (): void {
    $cancelled = webScheduledVisit('2026-10-21');
    app(VisitScheduler::class)->cancel($cancelled, $this->admin);
    $this->travelTo('2026-10-22 01:00:00');

    $this->artisan('review:flag-unvisited-stops')->assertSuccessful();

    expect($cancelled->routeStop->fresh()->needs_review)->toBeFalse();
});
