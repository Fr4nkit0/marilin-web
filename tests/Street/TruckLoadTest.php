<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Routes\Routes\Models\Route;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-20 08:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Administrador Marilin']);
    $this->driverUser = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-20', 'status' => 'PLANNED']);
    $this->water = Product::factory()->create(['name' => 'Bidón 20L', 'enabled' => true]);
    $this->soda = Product::factory()->create(['name' => 'Sifón 1.5L', 'enabled' => true]);
});

/**
 * @param  array<int, array{product_id: int, quantity: mixed}>  $items
 */
function loadTruck(array $items): TestResponse
{
    return test()->actingAs(test()->admin)
        ->post(route('routes.loads.store', test()->route), ['items' => $items]);
}

function stockOf(Product $product): float
{
    return (float) RouteInventory::query()
        ->where('route_id', test()->route->id)
        ->where('product_id', $product->id)
        ->value('current_quantity');
}

test('the admin registers the initial truck load and it becomes the truck stock', function (): void {
    loadTruck([
        ['product_id' => $this->water->id, 'quantity' => 60],
        ['product_id' => $this->soda->id, 'quantity' => 30],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(stockOf($this->water))->toBe(60.0)
        ->and(stockOf($this->soda))->toBe(30.0)
        ->and(RouteInventoryLoad::query()->where('route_id', $this->route->id)->get()->map->only(['product_id', 'quantity', 'created_by'])->all())->toEqual([
            ['product_id' => $this->water->id, 'quantity' => '60.00', 'created_by' => $this->admin->id],
            ['product_id' => $this->soda->id, 'quantity' => '30.00', 'created_by' => $this->admin->id],
        ]);
});

test('a mid-day reload adds to what is left in the truck and keeps both loads in the history', function (): void {
    loadTruck([['product_id' => $this->water->id, 'quantity' => 60]]);
    RouteInventory::query()->where('route_id', $this->route->id)->update(['current_quantity' => 12]);

    $this->travelTo('2026-10-20 13:10:00');
    loadTruck([['product_id' => $this->water->id, 'quantity' => 20]])->assertSessionHasNoErrors();

    expect(stockOf($this->water))->toBe(32.0)
        ->and(RouteInventoryLoad::query()->where('route_id', $this->route->id)->count())->toBe(2);

    $this->get(route('routes.index', ['date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Routes/Index')
            ->where('routes.0.truckStock.0.productName', 'Bidón 20L')
            ->where('routes.0.truckStock.0.loaded', 80)
            ->where('routes.0.truckStock.0.current', 32)
            ->where('routes.0.loadHistory.0.loadedAt', '08:00')
            ->where('routes.0.loadHistory.0.quantity', 60)
            ->where('routes.0.loadHistory.1.loadedAt', '13:10')
            ->where('routes.0.loadHistory.1.quantity', 20)
            ->where('routes.0.loadHistory.1.loadedBy', 'Administrador Marilin')
            ->where('routes.0.canLoad', true)
            ->has('products', 2));
});

test('the driver app gets the reloaded stock in its next pull', function (): void {
    loadTruck([['product_id' => $this->water->id, 'quantity' => 60]]);
    Sanctum::actingAs($this->driverUser, ['*']);
    $first = $this->getJson('/api/v1/sync/pull')->assertOk();

    $this->travelTo('2026-10-20 13:10:00');
    loadTruck([['product_id' => $this->water->id, 'quantity' => 20]]);

    Sanctum::actingAs($this->driverUser, ['*']);
    $next = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($first->json('meta.cursor')))->assertOk();

    expect(collect($next->json('data.route_inventories'))->firstWhere('product_id', $this->water->id)['current_quantity'])->toEqual('80.00');
});

test('a load needs whole positive quantities of known products', function (mixed $quantity): void {
    loadTruck([['product_id' => $this->water->id, 'quantity' => $quantity]])->assertSessionHasErrors('items.0.quantity');

    expect(session('errors')->first('items.0.quantity'))->toStartWith('Las cantidades');

    expect(RouteInventoryLoad::query()->count())->toBe(0);
})->with([0, -5, 2.5, 'diez']);

test('a load cannot repeat a product or point at an unknown one', function (): void {
    loadTruck([
        ['product_id' => $this->water->id, 'quantity' => 10],
        ['product_id' => $this->water->id, 'quantity' => 5],
    ])->assertSessionHasErrors('items.1.product_id');

    loadTruck([['product_id' => 99999, 'quantity' => 10]])->assertSessionHasErrors('items.0.product_id');

    expect(RouteInventoryLoad::query()->count())->toBe(0);
});

test('a finished route takes no more loads', function (): void {
    $this->route->update(['status' => 'COMPLETED']);

    loadTruck([['product_id' => $this->water->id, 'quantity' => 10]])->assertSessionHasErrors('items');

    expect(RouteInventoryLoad::query()->count())->toBe(0);
});

test('only an admin can load a truck', function (): void {
    $this->actingAs($this->driverUser)
        ->post(route('routes.loads.store', $this->route), ['items' => [['product_id' => $this->water->id, 'quantity' => 10]]])
        ->assertForbidden();

    expect(RouteInventoryLoad::query()->count())->toBe(0);
});

test('the cash close adds up every load of a product instead of listing each one', function (): void {
    loadTruck([['product_id' => $this->water->id, 'quantity' => 60]]);
    $this->travelTo('2026-10-20 13:10:00');
    loadTruck([['product_id' => $this->water->id, 'quantity' => 20]]);

    $this->get(route('cash-close.index', ['repartidor_id' => $this->driver->id, 'date' => '2026-10-20']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('settlement.stockLoaded', 1)
            ->where('settlement.stockLoaded.0.product', 'Bidón 20L')
            ->where('settlement.stockLoaded.0.quantity', 80)
            ->where('settlement.stockLoaded.0.loads', 2));
});

test('a reload made in the same minute as the first load is still a reload', function (): void {
    loadTruck([['product_id' => $this->water->id, 'quantity' => 60], ['product_id' => $this->soda->id, 'quantity' => 30]]);
    $this->travel(20)->seconds();
    loadTruck([['product_id' => $this->water->id, 'quantity' => 5]]);

    $this->get(route('routes.index', ['date' => '2026-10-20']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.0.loadHistory.0.isInitial', true)
            ->where('routes.0.loadHistory.1.isInitial', true)
            ->where('routes.0.loadHistory.2.isInitial', false)
            ->where('routes.0.loadHistory.2.loadedAt', '08:00'));
});
