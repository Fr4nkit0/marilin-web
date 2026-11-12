<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    // 2026-10-23 is a Friday.
    $this->travelTo('2026-10-23 07:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->absent = coverDriver('Ana Ausente');
    $this->cover = coverDriver('Beto Cubre');
    $this->other = coverDriver('Carla Cubre');
    $this->source = Route::factory()->undispatched()->create(['assigned_driver_id' => $this->absent->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
    $this->stops = collect(range(1, 3))->map(fn () => coverStop($this->source, coverCustomer($this->absent)));
});

function coverDriver(string $name, bool $enabled = true): Driver
{
    return Driver::factory()->create([
        'user_id' => User::factory()->create(['name' => $name, 'enabled' => true])->id,
        'enabled' => $enabled,
    ]);
}

/**
 * A customer of the driver visited on Fridays, with its primary address.
 */
function coverCustomer(Driver $driver): Customer
{
    $customer = Customer::factory()->create(['driver_id' => $driver->id, 'enabled' => true]);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true]);
    CustomerPreference::factory()->create(['customer_id' => $customer->id, 'day_of_week' => 5, 'time_window_start' => '09:00:00', 'enabled' => true]);

    return $customer;
}

function coverStop(Route $route, Customer $customer): RouteStop
{
    return RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $customer->primaryAddress->id,
    ]);
}

/**
 * @param  array<int, int>  $stopIds
 */
function moveStops(Route $from, array $stopIds, ?int $driverId, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->admin)
        ->from(route('routes.index'))
        ->post(route('routes.stops.move', $from), ['stop_ids' => $stopIds, 'driver_id' => $driverId]);
}

/**
 * Everything a move may write: every stop and route, as stored.
 *
 * @return array<string, mixed>
 */
function coverSnapshot(): array
{
    return [
        'stops' => RouteStop::query()->orderBy('id')->get(['id', 'route_id', 'moved_from_route_id', 'enabled', 'updated_at'])->toArray(),
        'routes' => Route::query()->orderBy('id')->get(['id', 'assigned_driver_id', 'status', 'dispatched_at', 'updated_at'])->toArray(),
        'owners' => Customer::query()->orderBy('id')->pluck('driver_id', 'id')->all(),
    ];
}

test('the selected stops move to the destination driver\'s route of the day, created planned and not dispatched', function (): void {
    [$first, $second, $staying] = $this->stops;
    $owners = Customer::query()->orderBy('id')->pluck('driver_id', 'id')->all();

    moveStops($this->source, [$first->id, $second->id], $this->cover->id)
        ->assertRedirect(route('routes.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Paradas movidas a la ruta de Beto Cubre.');

    $destination = Route::query()->where('assigned_driver_id', $this->cover->id)->sole();
    expect($destination->date->toDateString())->toBe('2026-10-23')
        ->and($destination->status)->toBe('PLANNED')
        ->and($destination->dispatched_at)->toBeNull()
        ->and($destination->enabled)->toBeTrue()
        // The same stop rows move: nothing is copied.
        ->and(RouteStop::query()->count())->toBe(3)
        ->and($first->fresh()->only(['route_id', 'moved_from_route_id']))->toBe(['route_id' => $destination->id, 'moved_from_route_id' => $this->source->id])
        ->and($second->fresh()->only(['route_id', 'moved_from_route_id']))->toBe(['route_id' => $destination->id, 'moved_from_route_id' => $this->source->id])
        ->and($staying->fresh()->only(['route_id', 'moved_from_route_id']))->toBe(['route_id' => $this->source->id, 'moved_from_route_id' => null])
        // A cover never changes who owns the customer.
        ->and(Customer::query()->orderBy('id')->pluck('driver_id', 'id')->all())->toBe($owners)
        ->and($this->source->fresh()->only(['status', 'dispatched_at']))->toBe(['status' => 'PLANNED', 'dispatched_at' => null]);
});

test('the stops can be split among several drivers, reusing a destination route that already exists', function (): void {
    [$first, $second, $third] = $this->stops;
    $existing = Route::factory()->undispatched()->create(['assigned_driver_id' => $this->other->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
    $own = coverStop($existing, coverCustomer($this->other));

    moveStops($this->source, [$first->id], $this->cover->id)->assertSessionHasNoErrors();
    moveStops($this->source, [$second->id, $third->id], $this->other->id)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Paradas movidas a la ruta de Carla Cubre.');

    $coverRoute = Route::query()->where('assigned_driver_id', $this->cover->id)->sole();
    expect(Route::query()->count())->toBe(3)
        ->and($first->fresh()->route_id)->toBe($coverRoute->id)
        ->and($existing->stops()->orderBy('id')->pluck('id')->all())->toBe([$second->id, $third->id, $own->id])
        ->and($own->fresh()->moved_from_route_id)->toBeNull()
        ->and($third->fresh()->moved_from_route_id)->toBe($this->source->id)
        ->and($this->source->stops()->count())->toBe(0)
        ->and($existing->fresh()->only(['status', 'dispatched_at']))->toBe(['status' => 'PLANNED', 'dispatched_at' => null]);
});

test('a covered stop keeps the absent driver\'s route when moved on, and clears it when moved back', function (): void {
    [$first] = $this->stops;

    moveStops($this->source, [$first->id], $this->cover->id)->assertSessionHasNoErrors();
    $coverRoute = Route::query()->where('assigned_driver_id', $this->cover->id)->sole();

    moveStops($coverRoute, [$first->id], $this->other->id)->assertSessionHasNoErrors();
    $otherRoute = Route::query()->where('assigned_driver_id', $this->other->id)->sole();
    expect($first->fresh()->only(['route_id', 'moved_from_route_id']))->toBe(['route_id' => $otherRoute->id, 'moved_from_route_id' => $this->source->id]);

    moveStops($otherRoute, [$first->id], $this->absent->id)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Paradas movidas a la ruta de Ana Ausente.');
    expect($first->fresh()->only(['route_id', 'moved_from_route_id']))->toBe(['route_id' => $this->source->id, 'moved_from_route_id' => null])
        ->and(Route::query()->count())->toBe(3);
});

test('a moved stop keeps the visit the admin scheduled on it', function (): void {
    [$first] = $this->stops;
    $visit = ScheduledVisit::factory()->create(['customer_id' => $first->customer_id, 'date' => '2026-10-23', 'route_stop_id' => $first->id]);
    $first->update(['scheduled_visit_id' => $visit->id, 'note' => 'Tocar timbre']);

    moveStops($this->source, [$first->id], $this->cover->id)->assertSessionHasNoErrors();

    $moved = $first->fresh();
    expect($moved->route->assigned_driver_id)->toBe($this->cover->id)
        ->and($moved->scheduled_visit_id)->toBe($visit->id)
        ->and($moved->note)->toBe('Tocar timbre')
        ->and($visit->fresh()->route_stop_id)->toBe($first->id);
});

test('a rejected move changes no stop and creates no route', function (Closure $arrange, string $field, string $message): void {
    [$first, $second] = $this->stops;
    [$stopIds, $driverId] = $arrange($first, $second);
    $before = coverSnapshot();

    $this->travelTo('2026-10-23 07:05:00');
    moveStops($this->source, $stopIds, $driverId)
        ->assertRedirect(route('routes.index'))
        ->assertSessionHasErrors([$field => $message]);

    expect(coverSnapshot())->toEqual($before);
})->with([
    'source dispatched' => [function (RouteStop $first): array {
        test()->source->update(['dispatched_at' => now()]);

        return [[$first->id], test()->cover->id];
    }, 'move', 'La ruta ya fue despachada: no se pueden mover sus paradas.'],
    'source in progress' => [function (RouteStop $first): array {
        test()->source->update(['status' => 'IN_PROGRESS']);

        return [[$first->id], test()->cover->id];
    }, 'move', 'Solo se pueden mover paradas de una ruta planificada.'],
    'source cancelled' => [function (RouteStop $first): array {
        test()->source->update(['status' => 'CANCELLED']);

        return [[$first->id], test()->cover->id];
    }, 'move', 'Solo se pueden mover paradas de una ruta planificada.'],
    'destination dispatched' => [function (RouteStop $first): array {
        Route::factory()->create(['assigned_driver_id' => test()->cover->id, 'date' => '2026-10-23', 'status' => 'PLANNED', 'dispatched_at' => now()]);

        return [[$first->id], test()->cover->id];
    }, 'move', 'La ruta de Beto Cubre ya fue despachada.'],
    'destination completed' => [function (RouteStop $first): array {
        Route::factory()->undispatched()->create(['assigned_driver_id' => test()->cover->id, 'date' => '2026-10-23', 'status' => 'COMPLETED']);

        return [[$first->id], test()->cover->id];
    }, 'move', 'La ruta de Beto Cubre está cerrada.'],
    'destination cancelled' => [function (RouteStop $first): array {
        Route::factory()->undispatched()->create(['assigned_driver_id' => test()->cover->id, 'date' => '2026-10-23', 'status' => 'CANCELLED']);

        return [[$first->id], test()->cover->id];
    }, 'move', 'La ruta de Beto Cubre está cerrada.'],
    'destination driver disabled' => [function (RouteStop $first): array {
        test()->cover->update(['enabled' => false]);

        return [[$first->id], test()->cover->id];
    }, 'driver_id', 'Elegí un repartidor habilitado.'],
    'destination driver unknown' => [fn (RouteStop $first): array => [[$first->id], 999999], 'driver_id', 'Elegí un repartidor habilitado.'],
    'same driver' => [fn (RouteStop $first): array => [[$first->id], test()->absent->id], 'driver_id', 'Elegí un repartidor distinto al de la ruta.'],
    'a stop done' => [function (RouteStop $first, RouteStop $second): array {
        Visit::factory()->finished()->create(['route_stop_id' => $second->id, 'customer_id' => $second->customer_id, 'driver_id' => test()->absent->id]);

        return [[$first->id, $second->id], test()->cover->id];
    }, 'move', 'Alguna de las paradas ya no está pendiente.'],
    'a stop rescheduled' => [function (RouteStop $first, RouteStop $second): array {
        coverStop(test()->source, $second->customer)->update(['is_rescheduled_from' => $second->id]);

        return [[$first->id, $second->id], test()->cover->id];
    }, 'move', 'Alguna de las paradas ya no está pendiente.'],
    'a stop disabled' => [function (RouteStop $first, RouteStop $second): array {
        $second->update(['enabled' => false]);

        return [[$first->id, $second->id], test()->cover->id];
    }, 'move', 'Alguna de las paradas ya no está pendiente.'],
    'a stop of another route' => [function (RouteStop $first): array {
        $elsewhere = Route::factory()->undispatched()->create(['assigned_driver_id' => test()->other->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);

        return [[$first->id, coverStop($elsewhere, coverCustomer(test()->other))->id], test()->cover->id];
    }, 'move', 'Alguna de las paradas no es de esta ruta.'],
    'no stop selected' => [fn (): array => [[], test()->cover->id], 'stop_ids', 'Elegí al menos una parada.'],
    'no destination' => [fn (RouteStop $first): array => [[$first->id], null], 'driver_id', 'Elegí un repartidor.'],
]);

test('a user who cannot load trucks cannot move stops', function (): void {
    $before = coverSnapshot();

    moveStops($this->source, [$this->stops[0]->id], $this->cover->id, User::factory()->create(['enabled' => true]))->assertForbidden();

    expect(coverSnapshot())->toEqual($before);
});

test('routes:generate does not put a moved customer back on the absent driver\'s route, and still completes it', function (): void {
    [$first] = $this->stops;
    $missing = coverCustomer($this->absent);

    moveStops($this->source, [$first->id], $this->cover->id)->assertSessionHasNoErrors();
    $coverRoute = Route::query()->where('assigned_driver_id', $this->cover->id)->sole();
    $coversOwn = coverCustomer($this->cover);

    $this->artisan('routes:generate')->assertSuccessful();

    expect(RouteStop::query()->where('customer_id', $first->customer_id)->pluck('route_id')->all())->toBe([$coverRoute->id])
        // The generator still adds the customers missing from both planned routes.
        ->and($this->source->stops()->where('customer_id', $missing->id)->count())->toBe(1)
        ->and($coverRoute->stops()->where('customer_id', $coversOwn->id)->count())->toBe(1)
        ->and($this->source->stops()->count())->toBe(3)
        ->and(Route::query()->count())->toBe(2);
});

test('routes:generate gives a stop to the new owner of a customer reassigned after its old driver\'s route was built', function (): void {
    [$first] = $this->stops;
    Customer::query()->whereKey($first->customer_id)->update(['driver_id' => $this->cover->id]);

    $this->artisan('routes:generate')->assertSuccessful();

    $coverRoute = Route::query()->where('assigned_driver_id', $this->cover->id)->sole();
    expect($coverRoute->stops()->where('customer_id', $first->customer_id)->count())->toBe(1)
        ->and($first->fresh()->route_id)->toBe($this->source->id);
});

test('the stop a reassigned customer left on its old driver\'s route cannot be moved', function (): void {
    [$first] = $this->stops;
    Customer::query()->whereKey($first->customer_id)->update(['driver_id' => $this->cover->id]);
    $before = coverSnapshot();

    moveStops($this->source, [$first->id], $this->other->id)
        ->assertSessionHasErrors(['move' => 'Alguna de las paradas es de un cliente que ya no es de este repartidor.']);

    expect(coverSnapshot())->toBe($before);
});

test('a stop does not move to a route where its customer already has an open stop', function (): void {
    [$first] = $this->stops;
    $coverRoute = Route::factory()->undispatched()->create(['assigned_driver_id' => $this->cover->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
    coverStop($coverRoute, $first->customer);
    $before = coverSnapshot();

    moveStops($this->source, [$first->id], $this->cover->id)
        ->assertSessionHasErrors(['move' => 'Algún cliente ya tiene una parada pendiente en la ruta de Beto Cubre.']);

    expect(coverSnapshot())->toBe($before);
});

test('covering a route never leaves two open stops of one customer on the destination route', function (): void {
    [$first, $second] = $this->stops;
    Customer::query()->whereKey($first->customer_id)->update(['driver_id' => $this->cover->id]);
    $this->artisan('routes:generate')->assertSuccessful();

    moveStops($this->source, [$second->id], $this->cover->id)->assertSessionHasNoErrors();

    $coverRoute = Route::query()->where('assigned_driver_id', $this->cover->id)->sole();
    expect($coverRoute->stops()->enabled()->notDone()->where('customer_id', $first->customer_id)->count())->toBe(1)
        ->and($second->fresh()->route_id)->toBe($coverRoute->id);
});

test('routes:generate gives no stop back to a route whose only stop was moved', function (): void {
    $lone = coverDriver('Diego Solo');
    $loneRoute = Route::factory()->undispatched()->create(['assigned_driver_id' => $lone->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
    $stop = coverStop($loneRoute, coverCustomer($lone));

    moveStops($loneRoute, [$stop->id], $this->cover->id)->assertSessionHasNoErrors();

    $this->artisan('routes:generate')->assertSuccessful();

    expect(RouteStop::query()->where('customer_id', $stop->customer_id)->pluck('id')->all())->toBe([$stop->id])
        ->and($loneRoute->stops()->count())->toBe(0);
});

test('the routes page tells which routes can lend stops and where a covered stop comes from', function (): void {
    [$first] = $this->stops;
    $dispatched = Route::factory()->create(['assigned_driver_id' => $this->other->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
    moveStops($this->source, [$first->id], $this->cover->id)->assertSessionHasNoErrors();

    $routes = collect($this->actingAs($this->admin)->get(route('routes.index', ['date' => '2026-10-23']))->assertOk()
        ->viewData('page')['props']['routes'])->keyBy('id');
    $coverRoute = $routes->firstWhere('driver.name', 'Beto Cubre');

    expect($routes[$this->source->id]['canMoveStops'])->toBeTrue()
        ->and($routes[$dispatched->id]['canMoveStops'])->toBeFalse()
        ->and($coverRoute['canMoveStops'])->toBeTrue()
        ->and($coverRoute['stops'][0]['id'])->toBe($first->id)
        ->and($coverRoute['stops'][0]['movedFrom'])->toBe('Ana Ausente')
        ->and($routes[$this->source->id]['stops'][0]['movedFrom'])->toBeNull();

    $this->actingAs(User::factory()->create(['enabled' => true]))->get(route('routes.index', ['date' => '2026-10-23']))
        ->assertInertia(fn (Assert $page) => $page->where('routes.0.canMoveStops', false));
});
