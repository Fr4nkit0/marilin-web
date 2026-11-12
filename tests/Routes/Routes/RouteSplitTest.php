<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    // 2026-10-23 is a Friday.
    $this->travelTo('2026-10-23 07:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->absent = splitDriver('Juan Falta');
    $this->source = Route::factory()->undispatched()->create(['assigned_driver_id' => $this->absent->id, 'date' => '2026-10-23', 'status' => 'PLANNED']);
});

function splitDriver(string $name, bool $enabled = true): Driver
{
    return Driver::factory()->create([
        'user_id' => User::factory()->create(['name' => $name, 'enabled' => true])->id,
        'enabled' => $enabled,
    ]);
}

/**
 * A stop of the route for a new customer of the route's driver, at an address of the given neighborhood.
 */
function splitStop(Route $route, ?string $neighborhood, array $attributes = []): RouteStop
{
    $customer = Customer::factory()->create([
        'driver_id' => $route->assigned_driver_id,
        'enabled' => true,
        'name' => $attributes['customer'] ?? fake()->unique()->name(),
    ]);
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_primary' => true,
        'street' => $attributes['street'] ?? fake()->streetAddress(),
        'neighborhood' => $neighborhood,
    ]);

    return RouteStop::factory()->create([
        'route_id' => $route->id,
        'customer_id' => $customer->id,
        'address_id' => $address->id,
        'scheduled_time' => $attributes['time'] ?? null,
        'enabled' => $attributes['enabled'] ?? true,
    ]);
}

function splitRoute(Driver $driver, string $date = '2026-10-23', array $attributes = []): Route
{
    return Route::factory()->undispatched()->create(['assigned_driver_id' => $driver->id, 'date' => $date, 'status' => 'PLANNED', ...$attributes]);
}

function openSplit(Route $route, ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->admin)->get(route('routes.split', $route));
}

/**
 * @return array<string, mixed>
 */
function splitProps(TestResponse $response): array
{
    return $response->viewData('page')['props'];
}

/**
 * Everything a move may write: every stop and route, as stored.
 *
 * @return array<string, mixed>
 */
function splitSnapshot(): array
{
    return [
        'stops' => RouteStop::query()->orderBy('id')->get(['id', 'route_id', 'moved_from_route_id', 'enabled', 'updated_at'])->toArray(),
        'routes' => Route::query()->orderBy('id')->get(['id', 'assigned_driver_id', 'status', 'dispatched_at', 'updated_at'])->toArray(),
    ];
}

test('the movable stops are grouped by the stop address neighborhood, alphabetically, with "Sin barrio" last', function (): void {
    $viso = splitStop($this->source, 'Del Viso', ['customer' => 'Zulema', 'street' => 'Mitre 10', 'time' => '09:30:00']);
    $bourgLate = splitStop($this->source, 'Grand Bourg', ['customer' => 'Bruno', 'time' => '11:00:00']);
    $bourgEarly = splitStop($this->source, 'Grand Bourg', ['customer' => 'Alicia', 'time' => '08:00:00']);
    $noName = splitStop($this->source, null, ['customer' => 'Carlos']);
    $blank = splitStop($this->source, '  ', ['customer' => 'Diana']);
    // Not movable: done, rescheduled or disabled stops stay out, and so does a neighborhood left without movable stops.
    $done = splitStop($this->source, 'Alberti');
    Visit::factory()->finished()->create(['route_stop_id' => $done->id, 'customer_id' => $done->customer_id, 'driver_id' => $this->absent->id]);
    splitStop($this->source, 'Alberti', ['enabled' => false]);
    $rescheduled = splitStop($this->source, 'Alberti');
    RouteStop::factory()->create([
        'route_id' => splitRoute($this->absent, '2026-10-24')->id,
        'customer_id' => $rescheduled->customer_id,
        'address_id' => $rescheduled->address_id,
        'is_rescheduled_from' => $rescheduled->id,
    ]);

    $response = openSplit($this->source)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Routes/Routes/Split'));
    $props = splitProps($response);

    expect($props['route'])->toBe(['id' => $this->source->id, 'driverName' => 'Juan Falta', 'date' => '23/10/2026', 'rawDate' => '2026-10-23'])
        ->and(array_column($props['groups'], 'name'))->toBe(['Del Viso', 'Grand Bourg', 'Sin barrio'])
        ->and(array_column($props['groups'], 'neighborhood'))->toBe(['Del Viso', 'Grand Bourg', null])
        ->and($props['groups'][0]['stops'])->toBe([[
            'id' => $viso->id,
            'customer' => 'Zulema',
            'street' => 'Mitre 10',
            'neighborhood' => 'Del Viso',
            'scheduledTime' => '09:30',
        ]])
        // Within a neighborhood, in the order the driver would visit them.
        ->and(array_column($props['groups'][1]['stops'], 'id'))->toBe([$bourgEarly->id, $bourgLate->id])
        ->and(array_column($props['groups'][2]['stops'], 'id'))->toBe([$noName->id, $blank->id])
        ->and($props['groups'][2]['stops'][1]['neighborhood'])->toBeNull();
});

test('a stop left behind by a reassigned customer is not offered, so a neighborhood moves as a whole', function (): void {
    $kept = splitStop($this->source, 'Grand Bourg');
    $leftBehind = splitStop($this->source, 'Grand Bourg');
    $leftBehind->customer->update(['driver_id' => splitDriver('Nuevo Duenio')->id]);

    $groups = collect(splitProps(openSplit($this->source)->assertOk())['groups'])->keyBy('name');

    expect(collect($groups['Grand Bourg']['stops'])->pluck('id')->all())->toBe([$kept->id]);
});

test('each neighborhood suggests the other enabled drivers already delivering there that date, the busiest first', function (): void {
    splitStop($this->source, 'Grand Bourg');
    splitStop($this->source, 'Del Viso');
    splitStop($this->source, null);

    $franco = splitDriver('Franco');
    $francoRoute = splitRoute($franco);
    splitStop($francoRoute, 'Grand Bourg');
    splitStop($francoRoute, 'Grand Bourg');
    // The same neighborhood written with other case and spaces.
    splitStop($francoRoute, ' grand bourg ');
    splitStop($francoRoute, 'Del Viso');
    splitStop($francoRoute, null);

    $pedro = splitDriver('Pedro');
    $pedroRoute = splitRoute($pedro, attributes: ['status' => 'IN_PROGRESS', 'dispatched_at' => now()]);
    splitStop($pedroRoute, 'Grand Bourg');
    // A disabled stop is not delivered.
    splitStop($pedroRoute, 'Grand Bourg', ['enabled' => false]);

    $ana = splitDriver('Ana');
    splitStop(splitRoute($ana), 'Grand Bourg');

    // Not suggestions: a disabled driver, another date, a cancelled, completed or disabled route, the route's own driver.
    splitStop(splitRoute(splitDriver('Hugo Termino'), attributes: ['status' => 'COMPLETED']), 'Grand Bourg');
    splitStop(splitRoute(splitDriver('Dora Baja', enabled: false)), 'Grand Bourg');
    splitStop(splitRoute(splitDriver('Ernesto Otro Dia'), '2026-10-24'), 'Grand Bourg');
    splitStop(splitRoute(splitDriver('Flor Cancelada'), attributes: ['status' => 'CANCELLED']), 'Grand Bourg');
    splitStop(splitRoute(splitDriver('Gabi Deshabilitada'), attributes: ['enabled' => false]), 'Grand Bourg');
    splitStop($this->source, 'Grand Bourg');

    $props = splitProps(openSplit($this->source)->assertOk());
    $groups = collect($props['groups'])->keyBy('name');

    expect($groups['Grand Bourg']['suggestions'])->toBe([
        ['driverId' => $franco->id, 'driverName' => 'Franco', 'stops' => 3],
        ['driverId' => $ana->id, 'driverName' => 'Ana', 'stops' => 1],
        // Pedro already left with his truck: his route cannot take stops, so he is not suggested.
    ])
        ->and($groups['Del Viso']['suggestions'])->toBe([
            ['driverId' => $franco->id, 'driverName' => 'Franco', 'stops' => 1],
        ])
        // Nobody "delivers" in an unknown neighborhood.
        ->and($groups['Sin barrio']['suggestions'])->toBe([])
        // Any enabled driver but the route's can take a neighborhood, ordered by name.
        ->and(array_column($props['drivers'], 'name'))->toBe(['Ana', 'Ernesto Otro Dia', 'Flor Cancelada', 'Franco', 'Gabi Deshabilitada', 'Hugo Termino', 'Pedro']);
});

test('the split page reads in a fixed number of queries, whatever the stops', function (): void {
    $other = splitRoute(splitDriver('Franco'));
    splitStop($this->source, 'Grand Bourg');
    splitStop($other, 'Grand Bourg');

    DB::enableQueryLog();
    openSplit($this->source)->assertOk();
    $few = count(DB::getQueryLog());

    foreach (['Grand Bourg', 'Del Viso', 'Alberti', null] as $neighborhood) {
        splitStop($this->source, $neighborhood);
        splitStop(splitRoute(splitDriver(fake()->unique()->name())), $neighborhood);
    }

    DB::flushQueryLog();
    openSplit($this->source)->assertOk();

    expect(count(DB::getQueryLog()))->toBe($few);
});

test('a user who cannot load trucks cannot open the split page', function (): void {
    splitStop($this->source, 'Grand Bourg');

    openSplit($this->source, User::factory()->create(['enabled' => true]))->assertForbidden();
});

test('a route that is not planned or already dispatched cannot be split', function (array $attributes, string $message): void {
    $this->source->update($attributes);
    splitStop($this->source, 'Grand Bourg');
    $before = splitSnapshot();

    openSplit($this->source)
        ->assertRedirect(route('routes.index', ['date' => '2026-10-23']))
        ->assertSessionHasErrors(['split' => $message]);

    expect(splitSnapshot())->toEqual($before);
})->with([
    'dispatched' => [['dispatched_at' => '2026-10-23 06:30:00'], 'La ruta ya fue despachada: ya no se puede repartir.'],
    'in progress' => [['status' => 'IN_PROGRESS'], 'Solo se puede repartir una ruta planificada.'],
    'cancelled' => [['status' => 'CANCELLED'], 'Solo se puede repartir una ruta planificada.'],
]);

test('assigning a neighborhood from the split page moves its stops and comes back to the page without them', function (): void {
    $first = splitStop($this->source, 'Grand Bourg');
    $second = splitStop($this->source, 'Grand Bourg');
    $staying = splitStop($this->source, 'Del Viso');
    $franco = splitDriver('Franco');

    $this->actingAs($this->admin)
        ->from(route('routes.split', $this->source))
        ->post(route('routes.stops.move', $this->source), ['stop_ids' => [$first->id, $second->id], 'driver_id' => $franco->id])
        ->assertRedirect(route('routes.split', $this->source))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Paradas movidas a la ruta de Franco.');

    $destination = Route::query()->where('assigned_driver_id', $franco->id)->sole();
    expect($first->fresh()->route_id)->toBe($destination->id)
        ->and($second->fresh()->route_id)->toBe($destination->id)
        ->and($staying->fresh()->route_id)->toBe($this->source->id);

    $groups = splitProps(openSplit($this->source)->assertOk())['groups'];
    expect(array_column($groups, 'name'))->toBe(['Del Viso'])
        ->and(array_column($groups[0]['stops'], 'id'))->toBe([$staying->id]);

    // Once every stop is assigned, nothing is left to split.
    $this->actingAs($this->admin)
        ->from(route('routes.split', $this->source))
        ->post(route('routes.stops.move', $this->source), ['stop_ids' => [$staying->id], 'driver_id' => $franco->id])
        ->assertRedirect(route('routes.split', $this->source));

    expect(splitProps(openSplit($this->source)->assertOk())['groups'])->toBe([]);
});
