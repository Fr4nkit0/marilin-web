<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 18:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $this->route = Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-22', 'status' => 'IN_PROGRESS']);
    $this->water = Product::factory()->create(['enabled' => true, 'price' => 700]);
    $this->soda = Product::factory()->create(['enabled' => true, 'price' => 400]);
    RouteInventory::query()->create(['route_id' => $this->route->id, 'product_id' => $this->water->id, 'current_quantity' => 3, 'created_by' => $this->user->id]);
});

/**
 * A sale at the list price of the given products, so price is never a reason here unless a test says so.
 *
 * @param  array<int, array{0: Product, 1: int}>  $lines  product and quantity
 * @return array<string, mixed>
 */
function stockSale(array $lines): array
{
    $details = array_map(fn (array $line): array => ['product_id' => $line[0]->id, 'quantity' => $line[1], 'unit_price' => (float) $line[0]->price], $lines);

    return [
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'date' => '2026-10-22T13:30:00Z',
        'total' => array_sum(array_map(fn (array $detail): float => $detail['quantity'] * $detail['unit_price'], $details)),
        'is_credit_sale' => true,
        'details' => $details,
    ];
}

function truckStock(Product $product): ?float
{
    $quantity = RouteInventory::query()->where('route_id', test()->route->id)->where('product_id', $product->id)->value('current_quantity');

    return $quantity === null ? null : (float) $quantity;
}

test('a sale that leaves the truck stock below zero is still accepted and booked, but goes to review', function (): void {
    $payload = stockSale([[$this->water, 5]]);

    push(['sales' => [$payload]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reasons', ReviewReason::labels([ReviewReason::InsufficientStock->value]));

    $sale = Sale::query()->where('client_uuid', $payload['uuid'])->sole();

    expect($sale->needs_review)->toBeTrue()
        ->and($sale->review_reasons)->toBe([ReviewReason::InsufficientStock->value])
        ->and($sale->stock_route_id)->toBe($this->route->id)
        ->and(truckStock($this->water))->toBe(-2.0)
        ->and((float) CustomerAccountEntry::query()->where('sale_id', $sale->id)->sole()->amount)->toBe(3500.0);
});

test('a sale that uses up the truck stock exactly is clean', function (): void {
    $payload = stockSale([[$this->water, 3]]);

    push(['sales' => [$payload]])->assertOk()->assertJsonPath('data.sales.0.review_reasons', []);

    expect(Sale::query()->where('client_uuid', $payload['uuid'])->sole()->needs_review)->toBeFalse()
        ->and(truckStock($this->water))->toBe(0.0);
});

test('a sale of a product the truck was not loaded with goes to review and its stock starts below zero', function (): void {
    $payload = stockSale([[$this->water, 1], [$this->soda, 2]]);

    push(['sales' => [$payload]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reasons', ReviewReason::labels([ReviewReason::InsufficientStock->value]));

    expect(truckStock($this->soda))->toBe(-2.0)
        ->and(truckStock($this->water))->toBe(2.0);
});

test('a sale on a day the driver has no route discounts no stock and is not flagged for it', function (): void {
    $this->route->update(['date' => '2026-10-21']);
    $payload = stockSale([[$this->water, 9]]);

    push(['sales' => [$payload]])->assertOk()
        ->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.sales.0.review_reasons', []);

    expect(Sale::query()->where('client_uuid', $payload['uuid'])->sole()->stock_route_id)->toBeNull()
        ->and(truckStock($this->water))->toBe(3.0);
});

test('the stock reason joins the other reasons of the sale', function (): void {
    CustomerAgreement::factory()->create([
        'customer_id' => $this->customer->id, 'product_id' => $this->water->id, 'unit_price' => 600, 'valid_from' => '2026-10-01', 'valid_to' => null,
    ]);

    push(['sales' => [stockSale([[$this->water, 4]])]])->assertOk()
        ->assertJsonPath('data.sales.0.review_reasons', ReviewReason::labels([ReviewReason::PriceMismatch->value, ReviewReason::InsufficientStock->value]));
});

test('an oversold sale sent again answers the same reasons and discounts nothing twice', function (): void {
    $payload = stockSale([[$this->water, 5]]);

    $first = push(['sales' => [$payload]]);
    push(['sales' => [$payload]])->assertOk()
        ->assertJsonPath('data.sales.0.server_id', $first->json('data.sales.0.server_id'))
        ->assertJsonPath('data.sales.0.review_reasons', ReviewReason::labels([ReviewReason::InsufficientStock->value]));

    expect(truckStock($this->water))->toBe(-2.0);
});

test('the review inbox reads the stock reason in the language of the panel', function (): void {
    push(['sales' => [stockSale([[$this->water, 5]])]])->assertOk();

    $this->actingAs(User::factory()->superUser()->create())
        ->get(route('review.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.type', 'sale')
            ->where('items.data.0.reasons', ['La venta deja el stock del camión por debajo de cero.']));
});
