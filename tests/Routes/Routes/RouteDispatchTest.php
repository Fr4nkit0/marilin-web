<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Street\RouteStock\Enums\LoadType;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 00:30:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Administrador Marilin']);
    $this->driverUser = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'enabled' => true]);
    $this->address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
    $this->route = Route::factory()->undispatched()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20', 'status' => 'PLANNED']);
    $this->stop = RouteStop::factory()->create(['route_id' => $this->route->id, 'customer_id' => $this->customer->id, 'address_id' => $this->address->id]);
    $this->water = Product::factory()->create(['name' => 'Bidón 20L', 'enabled' => true]);
});

function dispatchRoute(?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->admin)->post(route('routes.dispatch', test()->route));
}

function loadWater(int $quantity, LoadType $type = LoadType::Load): void
{
    RouteInventoryLoad::factory()->create([
        'route_id' => test()->route->id,
        'product_id' => test()->water->id,
        'quantity' => $quantity,
        'type' => $type,
        'created_by' => test()->admin->id,
    ]);
    RouteInventory::query()->updateOrCreate(
        ['route_id' => test()->route->id, 'product_id' => test()->water->id],
        ['current_quantity' => $quantity, 'created_by' => test()->admin->id, 'enabled' => true],
    );
}

function pullAsDriver(?string $cursor = null): TestResponse
{
    Sanctum::actingAs(test()->driverUser, ['*']);

    return test()->getJson('/api/v1/sync/pull'.($cursor === null ? '' : '?cursor='.urlencode($cursor)))->assertOk();
}

test('bootstrap hides a route not dispatched yet, with its stops and truck inventory', function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    loadWater(60);
    Sanctum::actingAs($this->driverUser, ['*']);

    $this->getJson('/api/v1/sync/bootstrap')
        ->assertOk()
        ->assertJsonPath('data.route', null)
        ->assertJsonPath('data.stops', [])
        ->assertJsonPath('data.truck_inventory', [])
        ->assertJsonPath('data.customers.0.id', $this->customer->id);
});

test('pull sends no route, stop or inventory of a route not dispatched yet', function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    loadWater(60);

    $response = pullAsDriver();

    expect($response->json('data.routes'))->toBe([])
        ->and($response->json('data.route_stops'))->toBe([])
        ->and($response->json('data.route_inventories'))->toBe([])
        ->and(collect($response->json('data.customers'))->pluck('id')->all())->toBe([$this->customer->id]);
});

test('the admin dispatches a loaded route and it is recorded with who and when', function (): void {
    $this->travelTo('2026-10-20 08:30:00');
    loadWater(60);

    dispatchRoute()->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Ruta despachada.');

    $route = $this->route->fresh();
    expect($route->dispatched_at?->toDateTimeString())->toBe('2026-10-20 08:30:00')
        ->and($route->dispatched_by)->toBe($this->admin->id);
});

test('once dispatched, the next pull sends the route with every stop and its inventory, older than the cursor', function (): void {
    $this->travelTo('2026-10-20 08:00:00');
    loadWater(60);
    $cursor = pullAsDriver()->json('meta.cursor');

    $this->travelTo('2026-10-20 08:30:00');
    dispatchRoute()->assertSessionHasNoErrors();

    $this->travelTo('2026-10-20 08:31:00');
    $response = pullAsDriver($cursor);

    expect(collect($response->json('data.routes'))->pluck('id')->all())->toBe([$this->route->id])
        ->and(collect($response->json('data.route_stops'))->pluck('id')->all())->toBe([$this->stop->id])
        ->and(collect($response->json('data.route_inventories'))->pluck('current_quantity')->all())->toBe(['60.00']);

    Sanctum::actingAs($this->driverUser, ['*']);
    $this->getJson('/api/v1/sync/bootstrap')
        ->assertJsonPath('data.route.id', $this->route->id)
        ->assertJsonPath('data.stops.0.id', $this->stop->id)
        ->assertJsonPath('data.truck_inventory.0.product_id', $this->water->id);
});

test('dispatch is rejected with a message and nothing changes', function (Closure $arrange, string $message): void {
    $this->travelTo('2026-10-20 08:30:00');
    $arrange();
    $before = $this->route->fresh()->only(['dispatched_at', 'dispatched_by', 'status', 'updated_at']);

    dispatchRoute()->assertRedirect()->assertSessionHasErrors(['route' => $message]);

    expect($this->route->fresh()->only(['dispatched_at', 'dispatched_by', 'status', 'updated_at']))->toEqual($before);
})->with([
    'no load' => [fn () => null, 'Registrá la carga inicial antes de despachar la ruta.'],
    'only a correction' => [fn () => loadWater(5, LoadType::Correction), 'Registrá la carga inicial antes de despachar la ruta.'],
    'already dispatched' => [function (): void {
        loadWater(60);
        test()->route->update(['dispatched_at' => now()->subHour(), 'dispatched_by' => test()->admin->id]);
    }, 'La ruta ya fue despachada.'],
    'completed' => [function (): void {
        loadWater(60);
        test()->route->update(['status' => 'COMPLETED']);
    }, 'La ruta está cerrada.'],
    'cancelled' => [function (): void {
        loadWater(60);
        test()->route->update(['status' => 'CANCELLED']);
    }, 'La ruta está cerrada.'],
]);

test('a user who cannot load trucks cannot dispatch', function (): void {
    loadWater(60);

    dispatchRoute(User::factory()->create(['enabled' => true]))->assertForbidden();

    expect($this->route->fresh()->dispatched_at)->toBeNull();
});

test('a route built by routes:generate waits for the dispatch', function (): void {
    $this->route->stops()->delete();
    $this->route->delete();
    CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => now()->dayOfWeek, 'enabled' => true]);

    $this->artisan('routes:generate')->assertSuccessful();

    $route = Route::query()->where('assigned_driver_id', $this->driver->id)->sole();
    expect($route->dispatched_at)->toBeNull();

    Sanctum::actingAs($this->driverUser, ['*']);
    $this->getJson('/api/v1/sync/bootstrap')->assertJsonPath('data.route', null);
});

test('the routes page shows whether a route is dispatched and if it can be', function (): void {
    $this->travelTo('2026-10-20 08:30:00');

    $page = fn () => $this->actingAs($this->admin)->get(route('routes.index', ['date' => '2026-10-20']))->assertOk();

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('routes.0.dispatchedAt', null)
        ->where('routes.0.canDispatch', false));

    loadWater(60);
    $page()->assertInertia(fn (Assert $page) => $page->where('routes.0.canDispatch', true));

    dispatchRoute()->assertSessionHasNoErrors();
    $page()->assertInertia(fn (Assert $page) => $page
        ->where('routes.0.dispatchedAt', '08:30')
        ->where('routes.0.dispatchedBy', 'Administrador Marilin')
        ->where('routes.0.canDispatch', false)
        ->where('routes.0.canLoad', true));
});
