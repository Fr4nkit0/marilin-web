<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Routes\Visits\Models\Visit;
use App\Street\Payments\CancelPayment;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\CancelSale;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-30 08:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    $this->otherUser = User::factory()->create();
    $this->other = Driver::factory()->create(['user_id' => $this->otherUser->id]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id]);
    Sanctum::actingAs($this->user, ['*']);
});

function pulledSale(array $attributes): Sale
{
    return Sale::factory()->create(['customer_id' => test()->customer->id, 'date' => '2026-10-29 10:00:00', 'client_uuid' => (string) Str::uuid(), ...$attributes]);
}

function pulledPayment(array $attributes): Payment
{
    return Payment::factory()->create(['customer_id' => test()->customer->id, 'paid_at' => '2026-10-29 10:00:00', 'client_uuid' => (string) Str::uuid(), ...$attributes]);
}

function cancelSaleAt(Sale $sale, User $by, string $at, string $reason): void
{
    test()->travelTo($at);
    app(StreetOperation::class)->cancelSale(new CancelSale(sale: $sale, cancelledBy: $by, reason: $reason));
}

function cancelPaymentAt(Payment $payment, User $by, string $at, string $reason): void
{
    test()->travelTo($at);
    app(StreetOperation::class)->cancelPayment(new CancelPayment(payment: $payment, cancelledBy: $by, reason: $reason));
}

test('pull carries the cancellations of the driver\'s sales made inside the window, with who cancelled them', function (): void {
    $loose = pulledSale(['created_by' => $this->user->id]);
    $onVisit = pulledSale(['created_by' => $this->admin->id, 'visit_id' => Visit::factory()->create(['driver_id' => $this->driver->id])->id]);
    $byDriver = pulledSale(['created_by' => $this->user->id]);
    $before = pulledSale(['created_by' => $this->user->id]);
    $foreign = pulledSale(['created_by' => $this->otherUser->id]);
    $confirmed = pulledSale(['created_by' => $this->user->id]);

    cancelSaleAt($before, $this->admin, '2026-10-29 12:00:00', 'Vieja');
    cancelSaleAt($loose, $this->admin, '2026-10-30 09:00:00', 'Cargada dos veces');
    cancelSaleAt($onVisit, $this->admin, '2026-10-30 09:05:00', 'Precio mal');
    cancelSaleAt($byDriver, $this->user, '2026-10-30 09:10:00', 'No la quiso');
    cancelSaleAt($foreign, $this->admin, '2026-10-30 09:15:00', 'De otro');
    $this->travelTo('2026-10-30 10:00:00');

    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode('2026-10-30T11:00:00Z'))->assertOk();
    $events = collect($response->json('data.sale_cancellations'))->keyBy('sale_id');

    expect($events->keys()->sort()->values()->all())->toBe([$loose->id, $onVisit->id, $byDriver->id])
        ->and($events[$loose->id])->toBe([
            'sale_id' => $loose->id,
            'client_uuid' => $loose->client_uuid,
            'cancelled_at' => '2026-10-30T12:00:00.000000Z',
            'reason' => 'Cargada dos veces',
            'cancelled_by_admin' => true,
        ])
        ->and($events[$byDriver->id]['cancelled_by_admin'])->toBeFalse()
        ->and($events[$byDriver->id]['reason'])->toBe('No la quiso')
        ->and(collect($response->json('data.sale_cancellations'))->pluck('sale_id'))->not->toContain($confirmed->id);
});

test('pull carries the cancellations of the driver\'s payments made inside the window, and only theirs', function (): void {
    $loose = pulledPayment(['received_by' => $this->user->id]);
    $byDriver = pulledPayment(['received_by' => $this->user->id]);
    $before = pulledPayment(['received_by' => $this->user->id]);
    $foreign = pulledPayment(['received_by' => $this->otherUser->id]);
    $confirmed = pulledPayment(['received_by' => $this->user->id]);

    cancelPaymentAt($before, $this->admin, '2026-10-29 12:00:00', 'Vieja');
    cancelPaymentAt($loose, $this->admin, '2026-10-30 09:00:00', 'Monto mal');
    cancelPaymentAt($byDriver, $this->user, '2026-10-30 09:10:00', 'Duplicado');
    cancelPaymentAt($foreign, $this->admin, '2026-10-30 09:15:00', 'De otro');
    $this->travelTo('2026-10-30 10:00:00');

    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode('2026-10-30T11:00:00Z'))->assertOk();
    $events = collect($response->json('data.payment_cancellations'))->keyBy('payment_id');

    expect($events->keys()->sort()->values()->all())->toBe([$loose->id, $byDriver->id])
        ->and($events[$loose->id])->toBe([
            'payment_id' => $loose->id,
            'client_uuid' => $loose->client_uuid,
            'cancelled_at' => '2026-10-30T12:00:00.000000Z',
            'reason' => 'Monto mal',
            'cancelled_by_admin' => true,
        ])
        ->and($events[$byDriver->id]['cancelled_by_admin'])->toBeFalse()
        ->and(collect($response->json('data.payment_cancellations'))->pluck('payment_id'))->not->toContain($confirmed->id);
});

test('a first pull carries every cancellation of the driver', function (): void {
    $sale = pulledSale(['created_by' => $this->user->id]);
    cancelSaleAt($sale, $this->admin, '2026-10-01 09:00:00', 'Hace mucho');
    $this->travelTo('2026-10-30 10:00:00');

    expect(collect($this->getJson('/api/v1/sync/pull')->assertOk()->json('data.sale_cancellations'))->pluck('sale_id')->all())->toBe([$sale->id])
        ->and(Carbon::parse($this->getJson('/api/v1/sync/pull')->json('data.sale_cancellations.0.cancelled_at'))->setTimezone(config('app.timezone'))->toDateTimeString())
        ->toBe('2026-10-01 09:00:00');
});
