<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerAgreement;
use App\Models\CustomerBalance;
use App\Models\Driver;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

function pullIds(array $response, string $entity): array
{
    return collect($response['data'][$entity])->pluck('id')->sort()->values()->all();
}

test('pull without cursor returns everything of the driver since the beginning', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'updated_at' => now()->subYears(2)]);
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'updated_at' => now()->subYears(2)]);
    $balance = CustomerBalance::factory()->create(['customer_id' => $customer->id]);

    $response = $this->getJson('/api/v1/sync/pull')->assertOk();

    expect(pullIds($response->json(), 'customers'))->toBe([$customer->id])
        ->and(pullIds($response->json(), 'customer_addresses'))->toBe([$address->id])
        ->and(pullIds($response->json(), 'customer_balances'))->toBe([$balance->id])
        ->and($response->json('meta.is_last_page'))->toBeTrue()
        ->and($response->json('meta.cursor'))->not->toBeNull();
});

test('pull returns only records changed after the cursor, disabled ones included', function (): void {
    $old = Customer::factory()->create(['driver_id' => $this->driver->id, 'updated_at' => now()->subDays(3)]);
    $changed = Customer::factory()->create(['driver_id' => $this->driver->id, 'updated_at' => now()->subMinute()]);
    $disabled = Customer::factory()->create(['driver_id' => $this->driver->id, 'enabled' => false, 'updated_at' => now()->subMinute()]);
    $product = Product::factory()->create(['enabled' => false, 'updated_at' => now()->subMinute()]);
    $oldProduct = Product::factory()->create(['updated_at' => now()->subDays(3)]);

    $cursor = now()->subHour()->toIso8601String();
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();

    expect(pullIds($response->json(), 'customers'))->toBe([$changed->id, $disabled->id])
        ->and(pullIds($response->json(), 'products'))->toBe([$product->id])
        ->and(collect($response->json('data.customers'))->firstWhere('id', $disabled->id)['enabled'])->toBeFalse()
        ->and(collect($response->json('data.products'))->pluck('id'))->not->toContain($oldProduct->id)
        ->and(collect($response->json('data.customers'))->pluck('id'))->not->toContain($old->id);
});

test('a cursor sent in UTC is read as the same instant in Argentine time', function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    // 11:30 in Argentina is 14:30 UTC, after a cursor of 14:00 UTC.
    $changed = Customer::factory()->create(['driver_id' => $this->driver->id, 'updated_at' => '2026-10-22 11:30:00']);

    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode('2026-10-22T14:00:00Z'))->assertOk();

    expect(pullIds($response->json(), 'customers'))->toBe([$changed->id]);
});

test('pull returns the address floor as text', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $text = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'floor' => 'PB']);
    $numeric = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'floor' => '3']);

    $addresses = collect($this->getJson('/api/v1/sync/pull')->assertOk()->json('data.customer_addresses'))->keyBy('id');

    expect($addresses[$text->id]['floor'])->toBe('PB')
        ->and($addresses[$numeric->id]['floor'])->toBe('3');
});

test('pull returns the list price of products and a price change travels in the next pull', function (): void {
    $product = Product::factory()->create(['price' => 800, 'updated_at' => now()->subDays(3)]);

    $first = $this->getJson('/api/v1/sync/pull')->assertOk();
    expect(collect($first->json('data.products'))->firstWhere('id', $product->id)['price'])->toBe('800.00');

    $this->travel(5)->minutes();
    $product->update(['price' => 875.25]);

    $next = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($first->json('meta.cursor')))->assertOk();

    expect(pullIds($next->json(), 'products'))->toBe([$product->id])
        ->and($next->json('data.products.0.price'))->toBe('875.25');
});

test('pull does not expose data of another driver', function (): void {
    $other = Driver::factory()->create(['enabled' => true]);
    Customer::factory()->create(['driver_id' => $other->id]);
    $otherRoute = Route::factory()->create(['assigned_driver_id' => $other->id]);
    RouteStop::factory()->create(['route_id' => $otherRoute->id]);

    $response = $this->getJson('/api/v1/sync/pull')->assertOk();

    expect($response->json('data.customers'))->toBe([])
        ->and($response->json('data.routes'))->toBe([])
        ->and($response->json('data.route_stops'))->toBe([]);
});

test('pull paginates with a fixed page size and delivers the new cursor on the last page', function (): void {
    config(['sync.pull_page_size' => 2]);
    $customers = Customer::factory()->count(5)->create(['driver_id' => $this->driver->id]);

    $first = $this->getJson('/api/v1/sync/pull?page=1')->assertOk();
    expect($first->json('data.customers'))->toHaveCount(2)
        ->and($first->json('meta.is_last_page'))->toBeFalse()
        ->and($first->json('meta.cursor'))->toBeNull();

    $until = $first->json('meta.until');
    $seen = collect($first->json('data.customers'))->pluck('id');

    $second = $this->getJson('/api/v1/sync/pull?page=2&until='.urlencode($until))->assertOk();
    $seen = $seen->merge(collect($second->json('data.customers'))->pluck('id'));
    expect($second->json('meta.is_last_page'))->toBeFalse();

    $third = $this->getJson('/api/v1/sync/pull?page=3&until='.urlencode($until))->assertOk();
    $seen = $seen->merge(collect($third->json('data.customers'))->pluck('id'));

    expect($third->json('data.customers'))->toHaveCount(1)
        ->and($third->json('meta.is_last_page'))->toBeTrue()
        ->and($third->json('meta.cursor'))->toBe(Carbon::parse($until)->subSeconds(5)->toIso8601String())
        ->and($seen->sort()->values()->all())->toBe($customers->pluck('id')->sort()->values()->all());
});

test('pull rejects a malformed cursor', function (): void {
    $this->getJson('/api/v1/sync/pull?cursor=not-a-date')->assertUnprocessable();
});

test('pull sends the derived status of a stop and re-sends it when its visit changes', function (): void {
    $route = Route::factory()->create(['assigned_driver_id' => $this->driver->id]);
    $owned = Customer::factory()->create(['driver_id' => $this->driver->id, 'enabled' => true]);
    $stop = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $owned->id, 'updated_at' => now()->subDays(2)]);
    $visit = Visit::factory()->create([
        'route_stop_id' => $stop->id,
        'customer_id' => $stop->customer_id,
        'status' => 'FINISHED',
        'updated_at' => now()->subMinute(),
    ]);

    $cursor = now()->subHour()->toIso8601String();
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();

    expect(pullIds($response->json(), 'route_stops'))->toBe([$stop->id])
        ->and($response->json('data.route_stops.0.status'))->toBe('FINISHED')
        ->and($response->json('data.route_stops.0'))->not->toHaveKeys(['visit', 'rescheduled_to']);
});

test('pull scopes children and balances to the authenticated driver', function (): void {
    $other = Customer::factory()->create(['driver_id' => Driver::factory()->create()->id]);
    CustomerAddress::factory()->create(['customer_id' => $other->id]);
    CustomerBalance::factory()->create(['customer_id' => $other->id]);

    $response = $this->getJson('/api/v1/sync/pull')->assertOk();

    expect($response->json('data.customer_addresses'))->toBe([])
        ->and($response->json('data.customer_balances'))->toBe([])
        ->and($response->json('data.customer_container_balances'))->toBe([])
        ->and($response->json('data.customer_agreements'))->toBe([]);
});

test('pull keeps paging while any entity still has rows, even if others are exhausted', function (): void {
    config(['sync.pull_page_size' => 2]);
    $route = Route::factory()->create(['assigned_driver_id' => $this->driver->id]);
    $owned = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $stops = RouteStop::factory()->count(3)->create(['route_id' => $route->id, 'customer_id' => $owned->id]);

    $first = $this->getJson('/api/v1/sync/pull?page=1')->assertOk();
    $until = $first->json('meta.until');
    $second = $this->getJson('/api/v1/sync/pull?page=2&until='.urlencode($until))->assertOk();

    expect($first->json('meta.is_last_page'))->toBeFalse()
        ->and($second->json('meta.is_last_page'))->toBeTrue()
        ->and($second->json('data.customers'))->toBe([])
        ->and($second->json('data.route_stops'))->toHaveCount(1)
        ->and(collect($first->json('data.route_stops'))->merge($second->json('data.route_stops'))->pluck('id')->sort()->values()->all())
        ->toBe($stops->pluck('id')->sort()->values()->all());
});

test('pull re-sends an agreement whose validity ended inside the window even if its row did not change', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'updated_at' => now()->subYear()]);
    $expiredToday = CustomerAgreement::factory()->create([
        'customer_id' => $customer->id,
        'valid_from' => now()->subMonths(3)->toDateString(),
        'valid_to' => now()->toDateString(),
        'updated_at' => now()->subMonths(3),
    ]);
    $expiredLongAgo = CustomerAgreement::factory()->create([
        'customer_id' => $customer->id,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_to' => now()->subMonths(6)->toDateString(),
        'updated_at' => now()->subMonths(6),
    ]);

    $cursor = now()->subDay()->toIso8601String();
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();

    expect(pullIds($response->json(), 'customer_agreements'))->toBe([$expiredToday->id])
        ->and(collect($response->json('data.customer_agreements'))->pluck('id'))->not->toContain($expiredLongAgo->id);
});

test('pull sends the client_uuid of agreements, null for the ones created on the web', function (): void {
    $customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $fromApp = CustomerAgreement::factory()->create([
        'customer_id' => $customer->id, 'client_uuid' => '7b0a3c9e-5d0e-4f7e-9d57-0c6a4c1b2f10',
    ]);
    $fromWeb = CustomerAgreement::factory()->create(['customer_id' => $customer->id]);

    $rows = collect($this->getJson('/api/v1/sync/pull')->assertOk()->json('data.customer_agreements'))->keyBy('id');

    expect($rows[$fromApp->id]['client_uuid'])->toBe('7b0a3c9e-5d0e-4f7e-9d57-0c6a4c1b2f10')
        ->and($rows[$fromWeb->id])->toHaveKey('client_uuid')
        ->and($rows[$fromWeb->id]['client_uuid'])->toBeNull();
});

test('pull clamps a future until so the next cursor never jumps ahead of the server clock', function (): void {
    $future = now()->addDay()->toIso8601String();

    $response = $this->getJson('/api/v1/sync/pull?until='.urlencode($future))->assertOk();

    expect(Carbon::parse($response->json('meta.until'))->lte(now()))->toBeTrue()
        ->and(Carbon::parse($response->json('meta.cursor'))->lte(now()))->toBeTrue();
});

test('pull omits stops of customers that are disabled or no longer assigned, like the bootstrap', function (): void {
    $route = Route::factory()->create(['assigned_driver_id' => $this->driver->id]);
    $active = Customer::factory()->create(['driver_id' => $this->driver->id, 'enabled' => true]);
    $disabled = Customer::factory()->create(['driver_id' => $this->driver->id, 'enabled' => false]);
    $reassigned = Customer::factory()->create(['driver_id' => Driver::factory()->create()->id]);

    $kept = RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $active->id]);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $disabled->id]);
    RouteStop::factory()->create(['route_id' => $route->id, 'customer_id' => $reassigned->id]);

    $response = $this->getJson('/api/v1/sync/pull')->assertOk();

    expect(pullIds($response->json(), 'route_stops'))->toBe([$kept->id]);
});

test('pull re-sends a customer and its address received after the cursor even when the app modification date is older', function (): void {
    $customer = Customer::factory()->create([
        'driver_id' => $this->driver->id,
        'updated_at' => now()->subDays(2),
        'received_at' => now()->subMinute(),
    ]);
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'updated_at' => now()->subDays(2)]);
    $stale = Customer::factory()->create([
        'driver_id' => $this->driver->id,
        'updated_at' => now()->subDays(2),
        'received_at' => now()->subDays(2),
    ]);

    $cursor = now()->subHour()->toIso8601String();
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();

    expect(pullIds($response->json(), 'customers'))->toBe([$customer->id])
        ->and(pullIds($response->json(), 'customer_addresses'))->toBe([$address->id])
        ->and(collect($response->json('data.customers'))->pluck('id'))->not->toContain($stale->id);
});
