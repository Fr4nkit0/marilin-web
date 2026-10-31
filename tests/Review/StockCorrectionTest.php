<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Street\RouteStock\Enums\LoadType;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Sync/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 18:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Administrador Marilin']);
    $this->driverUser = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->driverUser->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->driverUser->id]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-22', 'status' => 'IN_PROGRESS']);
    $this->water = Product::factory()->create(['enabled' => true, 'name' => 'Bidón 20L', 'price' => 700]);
    $this->soda = Product::factory()->create(['enabled' => true, 'name' => 'Sifón 1.5L', 'price' => 400]);
    RouteInventory::query()->create(['route_id' => $this->route->id, 'product_id' => $this->water->id, 'current_quantity' => 3, 'created_by' => $this->admin->id]);

    // The driver sells 5 bidones with 3 on the truck: accepted and left for review.
    Sanctum::actingAs($this->driverUser, ['*']);
    $uuid = (string) Str::uuid();
    push(['sales' => [[
        'uuid' => $uuid,
        'customer_id' => $this->customer->id,
        'date' => '2026-10-22T13:30:00Z',
        'total' => 3500,
        'is_credit_sale' => true,
        'details' => [['product_id' => $this->water->id, 'quantity' => 5, 'unit_price' => 700]],
    ]]])->assertOk()->assertJsonPath('data.sales.0.review_reasons', ReviewReason::labels([ReviewReason::InsufficientStock->value]));
    $this->sale = Sale::query()->where('client_uuid', $uuid)->sole();
});

/**
 * @param  array<string, mixed>  $payload
 */
function correctLoad(array $payload, ?User $as = null, ?Sale $sale = null): TestResponse
{
    return test()->actingAs($as ?? test()->admin, 'web')
        ->post(route('review.stock-correction', ($sale ?? test()->sale)->id), $payload);
}

function waterOnTruck(): float
{
    return (float) RouteInventory::query()->where('route_id', test()->route->id)->where('product_id', test()->water->id)->value('current_quantity');
}

test('the inbox offers to correct the load of a sale that left the truck below zero, with the truck stock of each product sold', function (): void {
    $this->actingAs($this->admin)->get(route('review.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Review/Index')
        ->where('items.data.0.type', 'sale')
        ->where('items.data.0.stock_correction.route_id', $this->route->id)
        ->where('items.data.0.stock_correction.products', [['id' => $this->water->id, 'name' => 'Bidón 20L', 'current' => -2]]));
});

test('a sale flagged for another reason offers no load correction', function (): void {
    $this->sale->forceFill(['review_reasons' => [ReviewReason::PriceMismatch->value]])->save();

    $this->actingAs($this->admin)->get(route('review.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('items.data.0.type', 'sale')
        ->where('items.data.0.stock_correction', null));
});

test('correcting the load adds a correction load with its reason, fixes the truck stock and marks the sale reviewed', function (): void {
    correctLoad([
        'items' => [['product_id' => $this->water->id, 'quantity' => 2]],
        'reason' => 'Se anotaron 3 bidones y salieron 5.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $load = RouteInventoryLoad::query()->where('route_id', $this->route->id)->sole();
    $sale = $this->sale->fresh();

    expect(waterOnTruck())->toBe(0.0)
        ->and($load->only(['product_id', 'type', 'reason', 'created_by']))->toBe([
            'product_id' => $this->water->id,
            'type' => LoadType::Correction,
            'reason' => 'Se anotaron 3 bidones y salieron 5.',
            'created_by' => $this->admin->id,
        ])
        ->and((float) $load->quantity)->toBe(2.0)
        ->and($sale->reviewed_by)->toBe($this->admin->id)
        ->and($sale->reviewed_at)->not->toBeNull()
        ->and($sale->review_reasons)->toBe([ReviewReason::InsufficientStock->value]);
});

test('a load correction needs a reason and whole positive quantities of the products the sale sold, and changes nothing when refused', function (array $payload, string $field): void {
    correctLoad($payload)->assertSessionHasErrors($field);

    expect(waterOnTruck())->toBe(-2.0)
        ->and(RouteInventoryLoad::query()->count())->toBe(0)
        ->and($this->sale->fresh()->reviewed_at)->toBeNull();
})->with([
    'no reason' => fn (): array => [['items' => [['product_id' => test()->water->id, 'quantity' => 2]], 'reason' => ''], 'reason'],
    'no items' => fn (): array => [['items' => [], 'reason' => 'Carga mal anotada'], 'items'],
    'zero units' => fn (): array => [['items' => [['product_id' => test()->water->id, 'quantity' => 0]], 'reason' => 'Carga mal anotada'], 'items.0.quantity'],
    'fractional units' => fn (): array => [['items' => [['product_id' => test()->water->id, 'quantity' => 1.5]], 'reason' => 'Carga mal anotada'], 'items.0.quantity'],
    'a product the sale did not sell' => fn (): array => [['items' => [['product_id' => test()->soda->id, 'quantity' => 2]], 'reason' => 'Carga mal anotada'], 'items.0.product_id'],
]);

test('the load of a route already closed can still be corrected', function (): void {
    $this->route->update(['status' => 'COMPLETED', 'completed_at' => '2026-10-22 17:00:00']);

    correctLoad(['items' => [['product_id' => $this->water->id, 'quantity' => 2]], 'reason' => 'Carga mal anotada'])->assertSessionHasNoErrors();

    expect(waterOnTruck())->toBe(0.0);
});

test('only a sale still waiting for review because of the truck stock takes a load correction', function (): void {
    $payload = ['items' => [['product_id' => $this->water->id, 'quantity' => 2]], 'reason' => 'Carga mal anotada'];

    $this->sale->forceFill(['review_reasons' => [ReviewReason::PriceMismatch->value]])->save();
    correctLoad($payload)->assertNotFound();

    $this->sale->forceFill(['review_reasons' => [ReviewReason::InsufficientStock->value], 'reviewed_at' => now(), 'reviewed_by' => $this->admin->id])->save();
    correctLoad($payload)->assertNotFound();

    expect(waterOnTruck())->toBe(-2.0)->and(RouteInventoryLoad::query()->count())->toBe(0);
});

test('only an admin can correct a truck load', function (): void {
    correctLoad(['items' => [['product_id' => $this->water->id, 'quantity' => 2]], 'reason' => 'Carga mal anotada'], User::factory()->create(['is_super_user' => false]))
        ->assertForbidden();

    expect(waterOnTruck())->toBe(-2.0)
        ->and(RouteInventoryLoad::query()->count())->toBe(0)
        ->and($this->sale->fresh()->reviewed_at)->toBeNull();
});

test('the driver app gets the corrected truck stock in its next pull', function (): void {
    Sanctum::actingAs($this->driverUser, ['*']);
    $first = $this->getJson('/api/v1/sync/pull')->assertOk();
    expect(collect($first->json('data.route_inventories'))->firstWhere('product_id', $this->water->id)['current_quantity'])->toEqual('-2.00');

    $this->travelTo('2026-10-22 19:00:00');
    correctLoad(['items' => [['product_id' => $this->water->id, 'quantity' => 2]], 'reason' => 'Carga mal anotada'])->assertSessionHasNoErrors();

    Sanctum::actingAs($this->driverUser, ['*']);
    $next = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($first->json('meta.cursor')))->assertOk();

    expect(collect($next->json('data.route_inventories'))->firstWhere('product_id', $this->water->id)['current_quantity'])->toEqual('0.00');
});

test('Despacho y rutas shows the correction in the load history apart from the loads, with its reason', function (): void {
    correctLoad(['items' => [['product_id' => $this->water->id, 'quantity' => 2]], 'reason' => 'Carga mal anotada'])->assertSessionHasNoErrors();

    $this->actingAs($this->admin)->get(route('routes.index', ['date' => '2026-10-22']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('routes.0.loadHistory.0.quantity', 2)
        ->where('routes.0.loadHistory.0.isCorrection', true)
        ->where('routes.0.loadHistory.0.reason', 'Carga mal anotada')
        ->where('routes.0.routeStock.0.current', 0));
});
