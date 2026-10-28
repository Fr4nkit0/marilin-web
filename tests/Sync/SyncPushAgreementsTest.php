<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Review\ReviewReason;
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
    $this->product = Product::factory()->create(['enabled' => true]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agreementPayload(array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => test()->customer->id,
        'product_id' => test()->product->id,
        'unit_price' => 850.50,
        'valid_from' => '2026-10-22',
        'valid_to' => '2026-12-31',
        'reason' => 'Compra por mayor',
    ], $overrides);
}

test('push accepts an agreement of the driver, marks it for review and stores it', function (): void {
    $payload = agreementPayload();

    $response = push(['customer_agreements' => [$payload]])->assertOk();

    $agreement = CustomerAgreement::query()->where('client_uuid', $payload['uuid'])->sole();

    expect($response->json('data.customer_agreements.0'))->toBe([
        'uuid' => $payload['uuid'],
        'status' => 'accepted',
        'server_id' => $agreement->id,
        'review_reason' => 'Agreement created by a driver.',
    ])
        ->and(ReviewReason::DriverAgreement->label())->toBe('Acuerdo de precio creado por el repartidor.')
        ->and($agreement->customer_id)->toBe($this->customer->id)
        ->and($agreement->product_id)->toBe($this->product->id)
        ->and($agreement->unit_price)->toBe('850.50')
        ->and($agreement->valid_from->toDateString())->toBe('2026-10-22')
        ->and($agreement->valid_to->toDateString())->toBe('2026-12-31')
        ->and($agreement->reason)->toBe('Compra por mayor')
        ->and($agreement->created_by)->toBe($this->user->id)
        ->and($agreement->needs_review)->toBeTrue()
        ->and($agreement->review_reason)->toBe('Agreement created by a driver.')
        ->and($agreement->reviewed_at)->toBeNull();
});

test('push resolves the customer of an agreement by uuid and accepts it without end date or reason', function (): void {
    $payload = agreementPayload(['valid_to' => null, 'reason' => null]);
    unset($payload['customer_id']);
    $payload['customer_uuid'] = $this->customer->client_uuid ?? tap($this->customer)->update(['client_uuid' => (string) Str::uuid()])->client_uuid;

    push(['customer_agreements' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_agreements.0.status', 'accepted');

    $agreement = CustomerAgreement::query()->sole();
    expect($agreement->customer_id)->toBe($this->customer->id)
        ->and($agreement->valid_to)->toBeNull()
        ->and($agreement->reason)->toBeNull();
});

test('push replays an agreement by uuid with the same server id and creates nothing twice', function (): void {
    $payload = agreementPayload();

    $first = push(['customer_agreements' => [$payload]])->json('data.customer_agreements.0');
    $second = push(['customer_agreements' => [$payload]])->assertOk()->json('data.customer_agreements.0');

    expect($second)->toBe($first)
        ->and(CustomerAgreement::query()->count())->toBe(1);
});

test('push rejects an invalid agreement with its error and leaves the table unchanged', function (array $override, string $error): void {
    $existing = CustomerAgreement::factory()->create(['customer_id' => $this->customer->id]);
    $payload = agreementPayload($override);

    $result = push(['customer_agreements' => [$payload]])->assertOk()->json('data.customer_agreements.0');

    expect($result['status'])->toBe('rejected')
        ->and($result['server_id'])->toBeNull()
        ->and($result['review_reason'])->toBeNull()
        ->and($result['error'])->toContain($error)
        ->and(CustomerAgreement::query()->pluck('id')->all())->toBe([$existing->id]);
})->with([
    'zero price' => [['unit_price' => 0], 'unit price'],
    'three decimals' => [['unit_price' => 10.123], 'unit price'],
    'missing price' => [['unit_price' => null], 'unit price'],
    'unknown product' => [['product_id' => 999999], 'product id'],
    'bad from date' => [['valid_from' => '22/10/2026'], 'valid from'],
    'end before start' => [['valid_to' => '2026-10-21'], 'valid to'],
    'reason too long' => [['reason' => str_repeat('a', 501)], 'reason'],
    'unknown customer' => [['customer_id' => 999999], 'customer'],
]);

test('push rejects an agreement without customer reference or with a bad uuid', function (): void {
    $noCustomer = agreementPayload();
    unset($noCustomer['customer_id']);

    $results = push(['customer_agreements' => [$noCustomer, agreementPayload(['uuid' => 'nope'])]])
        ->assertOk()->json('data.customer_agreements');

    expect($results[0]['status'])->toBe('rejected')
        ->and($results[1]['status'])->toBe('rejected')
        ->and(CustomerAgreement::query()->count())->toBe(0);
});

test('push processes agreements after customers so an agreement can point at a customer of the same request', function (): void {
    $customerUuid = (string) Str::uuid();
    $agreement = agreementPayload();
    unset($agreement['customer_id']);
    $agreement['customer_uuid'] = $customerUuid;

    $response = push([
        'customers' => [[
            'uuid' => $customerUuid, 'name' => 'Nuevo Cliente', 'modified_at' => '2026-10-22T10:00:00Z',
            'address' => ['street' => 'Calle Falsa 123'],
        ]],
        'customer_agreements' => [$agreement],
    ])->assertOk();

    expect($response->json('data.customer_agreements.0.status'))->toBe('accepted')
        ->and(CustomerAgreement::query()->sole()->customer->client_uuid)->toBe($customerUuid);
});
