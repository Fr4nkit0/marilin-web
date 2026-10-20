<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
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
    $stop = RouteStop::factory()->create(['route_id' => $route->id, 'updated_at' => now()->subDays(2)]);
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
    Customer::factory()->create(['driver_id' => $this->driver->id]);
    $stops = RouteStop::factory()->count(3)->create(['route_id' => $route->id]);

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
