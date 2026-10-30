<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\Product;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Containers\MoveContainers;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/PushHelpers.php';

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
    $this->sifon = ContainerType::factory()->create();
    $this->visit = Visit::factory()->create(['customer_id' => $this->customer->id, 'driver_id' => $this->driver->id, 'created_by' => $this->user->id]);

    // The balances the customer held before the sale: 3 bidones and 2 sifones.
    foreach ([[$this->bidon, 3], [$this->sifon, 2]] as [$type, $quantity]) {
        app(StreetOperation::class)->moveContainers(new MoveContainers(
            customer: $this->customer,
            containerTypeId: $type->id,
            type: ContainerMovementType::Adjustment,
            quantity: $quantity,
            visit: null,
            recordedBy: $this->user,
        ));
    }
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function containerSale(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'visit_id' => test()->visit->id,
        'date' => '2026-10-22T13:30:00Z',
        'total' => 1400,
        'is_credit_sale' => true,
        'details' => [['product_id' => test()->water->id, 'quantity' => 2, 'unit_price' => 700]],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function saleMovement(ContainerType $type, string $movementType, int $quantity, array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'visit_id' => test()->visit->id,
        'container_type_id' => $type->id,
        'movement_type' => $movementType,
        'quantity' => $quantity,
    ], $overrides);
}

function containerBalance(ContainerType $type): int
{
    return CustomerContainerBalance::query()
        ->where('customer_id', test()->customer->id)
        ->where('container_type_id', $type->id)
        ->firstOrFail()->balance;
}

test('cancelling a pushed sale gives back the containers moved with it and leaves the visit movement without sale alone', function (): void {
    $sale = containerSale();
    $delivered = saleMovement($this->bidon, 'DELIVERED', 2, ['sale_uuid' => $sale['uuid']]);
    $returned = saleMovement($this->sifon, 'RETURNED', 1, ['sale_uuid' => $sale['uuid']]);
    $loose = saleMovement($this->bidon, 'DELIVERED', 1);

    // Container movements come first in the request, yet resolve the sale of the same push.
    $response = push(['container_movements' => [$delivered, $returned, $loose], 'sales' => [$sale]])->assertOk();

    $record = Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail();
    foreach ([0, 1, 2] as $index) {
        $response->assertJsonPath("data.container_movements.{$index}.status", 'accepted');
    }
    $movements = ContainerMovement::query()->whereIn('client_uuid', [$delivered['uuid'], $returned['uuid'], $loose['uuid']])->get()->keyBy('client_uuid');
    expect($movements[$delivered['uuid']]->sale_id)->toBe($record->id)
        ->and($movements[$returned['uuid']]->sale_id)->toBe($record->id)
        ->and($movements[$loose['uuid']]->sale_id)->toBeNull()
        ->and(containerBalance($this->bidon))->toBe(6)
        ->and(containerBalance($this->sifon))->toBe(1);

    push(['sale_cancellations' => [['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale['uuid'], 'cancelled_at' => '2026-10-22T15:00:00Z']]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    $reversals = ContainerMovement::query()->whereNotNull('reverses_movement_id')->orderBy('id')->get();
    expect(containerBalance($this->bidon))->toBe(4)
        ->and(containerBalance($this->sifon))->toBe(2)
        ->and($reversals)->toHaveCount(2)
        ->and($reversals->map->only(['reverses_movement_id', 'sale_id', 'container_type_id', 'movement_type', 'quantity', 'direction', 'visit_id', 'created_by'])->all())->toBe([
            [
                'reverses_movement_id' => $movements[$delivered['uuid']]->id, 'sale_id' => $record->id, 'container_type_id' => $this->bidon->id,
                'movement_type' => 'RETURNED', 'quantity' => 2, 'direction' => -1, 'visit_id' => $this->visit->id, 'created_by' => $this->user->id,
            ],
            [
                'reverses_movement_id' => $movements[$returned['uuid']]->id, 'sale_id' => $record->id, 'container_type_id' => $this->sifon->id,
                'movement_type' => 'DELIVERED', 'quantity' => 1, 'direction' => 1, 'visit_id' => $this->visit->id, 'created_by' => $this->user->id,
            ],
        ])
        ->and($movements[$loose['uuid']]->fresh()->only(['sale_id', 'quantity', 'movement_type']))->toBe(['sale_id' => null, 'quantity' => 1, 'movement_type' => 'DELIVERED'])
        ->and(ContainerMovement::query()->where('reverses_movement_id', $movements[$loose['uuid']]->id)->exists())->toBeFalse()
        ->and(ContainerMovement::query()->count())->toBe(7)
        // Dated when the driver cancelled, not when the push arrived.
        ->and($reversals->map(fn (ContainerMovement $reversal): string => $reversal->created_at->toIso8601String())->all())
        ->toBe(['2026-10-22T12:00:00-03:00', '2026-10-22T12:00:00-03:00']);
});

test('a sale cancellation rejected by the date rule gives no containers back', function (): void {
    $sale = containerSale();
    push(['sales' => [$sale], 'container_movements' => [saleMovement($this->bidon, 'DELIVERED', 2, ['sale_uuid' => $sale['uuid']])]])->assertOk();

    $this->travelTo('2026-10-23 09:00:00');
    push(['sale_cancellations' => [['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale['uuid'], 'cancelled_at' => '2026-10-23T11:00:00Z']]])
        ->assertOk()
        ->assertJsonPath('data.sale_cancellations.0.status', 'rejected')
        ->assertJsonPath('data.sale_cancellations.0.error', "The cancellation was not made on the record's day.");

    expect(containerBalance($this->bidon))->toBe(5)
        ->and(ContainerMovement::query()->whereNotNull('reverses_movement_id')->exists())->toBeFalse()
        ->and(ContainerMovement::query()->count())->toBe(3);
});

test('a sale, its containers and its cancellation in one push leave the balance as before, whatever the key order', function (array $keys): void {
    $sale = containerSale();
    $records = [
        'sale_cancellations' => [['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale['uuid'], 'cancelled_at' => '2026-10-22T15:00:00Z']],
        'container_movements' => [
            saleMovement($this->bidon, 'DELIVERED', 2, ['sale_uuid' => $sale['uuid']]),
            saleMovement($this->sifon, 'RETURNED', 1, ['sale_uuid' => $sale['uuid']]),
        ],
        'sales' => [$sale],
    ];

    $response = push(array_replace(array_flip($keys), $records))->assertOk();

    $response->assertJsonPath('data.sales.0.status', 'accepted')
        ->assertJsonPath('data.container_movements.0.status', 'accepted')
        ->assertJsonPath('data.container_movements.1.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.0.status', 'accepted');
    $originals = ContainerMovement::query()->whereNotNull('sale_id')->whereNull('reverses_movement_id')->pluck('id')->sort()->values()->all();
    expect(containerBalance($this->bidon))->toBe(3)
        ->and(containerBalance($this->sifon))->toBe(2)
        ->and(ContainerMovement::query()->whereNotNull('reverses_movement_id')->pluck('reverses_movement_id')->sort()->values()->all())->toBe($originals)
        ->and($originals)->toHaveCount(2);
})->with([
    'cancellation first' => [['sale_cancellations', 'container_movements', 'sales']],
    'sale first' => [['sales', 'container_movements', 'sale_cancellations']],
    'containers last' => [['sale_cancellations', 'sales', 'container_movements']],
]);

test('push rejects a container movement whose sale is already cancelled and moves nothing', function (string $by): void {
    $sale = containerSale();
    push(['sales' => [$sale]])->assertOk();
    push(['sale_cancellations' => [['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale['uuid'], 'cancelled_at' => '2026-10-22T15:00:00Z']]])
        ->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');
    $reference = $by === 'uuid' ? ['sale_uuid' => $sale['uuid']] : ['sale_id' => Sale::query()->where('client_uuid', $sale['uuid'])->value('id')];
    $movement = saleMovement($this->bidon, 'DELIVERED', 2, $reference);

    $result = push(['container_movements' => [$movement]])->assertOk()->json('data.container_movements.0');

    expect($result['status'])->toBe('rejected')
        ->and($result['server_id'])->toBeNull()
        ->and($result['error'])->toBe('The sale is cancelled.')
        ->and(ContainerMovement::query()->where('client_uuid', $movement['uuid'])->exists())->toBeFalse()
        ->and(ContainerMovement::query()->count())->toBe(2)
        ->and(containerBalance($this->bidon))->toBe(3);
})->with(['uuid', 'id']);

test('a replayed sale cancellation gives no containers back twice', function (): void {
    $sale = containerSale();
    push(['sales' => [$sale], 'container_movements' => [
        saleMovement($this->bidon, 'DELIVERED', 2, ['sale_uuid' => $sale['uuid']]),
        saleMovement($this->sifon, 'RETURNED', 1, ['sale_uuid' => $sale['uuid']]),
    ]])->assertOk();
    $cancellation = ['uuid' => (string) Str::uuid(), 'sale_uuid' => $sale['uuid'], 'cancelled_at' => '2026-10-22T15:00:00Z'];
    push(['sale_cancellations' => [$cancellation]])->assertOk()->assertJsonPath('data.sale_cancellations.0.status', 'accepted');

    push(['sale_cancellations' => [$cancellation, [...$cancellation, 'uuid' => (string) Str::uuid()]]])->assertOk()
        ->assertJsonPath('data.sale_cancellations.0.status', 'accepted')
        ->assertJsonPath('data.sale_cancellations.1.status', 'accepted');

    expect(containerBalance($this->bidon))->toBe(3)
        ->and(containerBalance($this->sifon))->toBe(2)
        ->and(ContainerMovement::query()->whereNotNull('reverses_movement_id')->count())->toBe(2)
        ->and(ContainerMovement::query()->count())->toBe(6);
});

test('push links a container movement to a sale by its server id', function (): void {
    $sale = containerSale();
    push(['sales' => [$sale]])->assertOk();
    $record = Sale::query()->where('client_uuid', $sale['uuid'])->firstOrFail();
    $movement = saleMovement($this->bidon, 'DELIVERED', 2, ['sale_id' => $record->id]);

    push(['container_movements' => [$movement]])->assertOk()->assertJsonPath('data.container_movements.0.status', 'accepted');

    expect(ContainerMovement::query()->where('client_uuid', $movement['uuid'])->sole()->sale_id)->toBe($record->id)
        ->and($record->containerMovements()->count())->toBe(1);
});

test('push rejects a container movement whose sale is of another customer or matches nothing, and moves nothing', function (string $case): void {
    $other = Customer::factory()->create(['driver_id' => $this->driver->id, 'created_by' => $this->user->id]);
    $sale = containerSale(['customer_id' => $other->id, 'visit_id' => null]);
    push(['sales' => [$sale]])->assertOk()->assertJsonPath('data.sales.0.status', 'accepted');
    $reference = match ($case) {
        'another customer by id' => ['sale_id' => Sale::query()->where('client_uuid', $sale['uuid'])->value('id')],
        'another customer by uuid' => ['sale_uuid' => $sale['uuid']],
        'unknown' => ['sale_uuid' => (string) Str::uuid()],
    };
    $movement = saleMovement($this->bidon, 'DELIVERED', 2, $reference);

    $result = push(['container_movements' => [$movement]])->assertOk()->json('data.container_movements.0');

    expect($result['status'])->toBe('rejected')
        ->and($result['server_id'])->toBeNull()
        ->and($result['error'])->toBe($case === 'unknown' ? 'The sale reference does not match any record.' : 'The sale belongs to another customer.')
        ->and(ContainerMovement::query()->where('client_uuid', $movement['uuid'])->exists())->toBeFalse()
        ->and(containerBalance($this->bidon))->toBe(3);
})->with(['another customer by id', 'another customer by uuid', 'unknown']);
