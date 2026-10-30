<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\CashDesk\DailyFigures;
use App\CashDesk\DayScope;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-22 18:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);

    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    Route::factory()->create(['assigned_driver_id' => $this->driver->id, 'date' => '2026-10-22', 'status' => 'IN_PROGRESS']);
    $this->water = Product::factory()->create(['enabled' => true, 'price' => 700]);
    $this->bidon = ContainerType::factory()->create();
});

/**
 * Pushes a loose delivery and return of 1 bidón each, and a sale of the day with a delivery of 2 bidones linked to it.
 */
function pushDayWithContainerSale(): string
{
    $sale = (string) Str::uuid();
    $movement = fn (string $type, int $quantity, array $extra = []): array => [
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'container_type_id' => test()->bidon->id,
        'movement_type' => $type,
        'quantity' => $quantity,
        ...$extra,
    ];

    test()->postJson('/api/v1/sync/push', [
        'sales' => [[
            'uuid' => $sale,
            'customer_id' => test()->customer->id,
            'date' => '2026-10-22T13:30:00Z',
            'total' => 1400,
            'is_credit_sale' => true,
            'details' => [['product_id' => test()->water->id, 'quantity' => 2, 'unit_price' => 700]],
        ]],
        'container_movements' => [$movement('DELIVERED', 1), $movement('RETURNED', 1), $movement('DELIVERED', 2, ['sale_uuid' => $sale])],
    ])->assertOk()->assertJsonPath('data.container_movements.2.status', 'accepted');

    return $sale;
}

/**
 * @return array{0: int, 1: int}
 */
function dayContainers(string $date): array
{
    $day = app(DailyFigures::class)->forDay($date, DayScope::all());

    return [$day->containersDelivered, $day->containersReturned];
}

test('a sale cancelled the same day counts neither its containers nor their give-back in the day figures', function (): void {
    $sale = pushDayWithContainerSale();
    expect(dayContainers('2026-10-22'))->toBe([3, 1]);

    $this->postJson('/api/v1/sync/push', ['sale_cancellations' => [['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale, 'cancelled_at' => '2026-10-22T15:00:00Z']]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    expect(dayContainers('2026-10-22'))->toBe([1, 1])
        ->and(app(DailyFigures::class)->forDay('2026-10-22', DayScope::driver($this->driver))->containerMovements)->toHaveCount(2)
        // The customer's balance and history keep the movements.
        ->and(CustomerContainerBalance::query()->where('customer_id', $this->customer->id)->sole()->balance)->toBe(0)
        ->and(ContainerMovement::query()->count())->toBe(4);
});

test('a sale cancelled offline at 23:50 and pushed after midnight shows its containers on neither day', function (): void {
    $sale = pushDayWithContainerSale();

    $this->travelTo('2026-10-23 00:00:13');
    $this->postJson('/api/v1/sync/push', ['sale_cancellations' => [['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale, 'cancelled_at' => '2026-10-23T02:50:00Z']]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    $reversal = ContainerMovement::query()->whereNotNull('reverses_movement_id')->sole();
    expect($reversal->created_at->toIso8601String())->toBe('2026-10-22T23:50:00-03:00')
        ->and(dayContainers('2026-10-22'))->toBe([1, 1])
        ->and(dayContainers('2026-10-23'))->toBe([0, 0]);
});
