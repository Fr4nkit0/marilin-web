<?php

declare(strict_types=1);

use App\Models\ContainerMovement;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\User;
use App\Models\Visit;
use App\Services\Sync\ReviewReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/Api/PushHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-23 12:00:00');
    $this->admin = User::factory()->superUser()->create();
    $this->actingAs($this->admin);
});

/**
 * @return array{driver: Driver, customer: Customer}
 */
function inboxDriver(string $driverName = 'Repartidor Uno', string $customerName = 'Cliente Uno'): array
{
    $driver = Driver::factory()->create(['user_id' => User::factory()->create(['name' => $driverName])->id]);
    $customer = Customer::factory()->create(['driver_id' => $driver->id, 'name' => $customerName]);

    return ['driver' => $driver, 'customer' => $customer];
}

/**
 * Creates one record of the given type that is waiting for review, on the given day.
 *
 * @param  array{driver: Driver, customer: Customer}  $who
 * @param  array<string, mixed>  $review
 */
function flaggedRecord(string $type, array $who, string $day = '2026-10-22', array $review = []): Model
{
    $review += ['needs_review' => true, 'review_reason' => "Reason for {$type}"];
    ['driver' => $driver, 'customer' => $customer] = $who;

    $record = match ($type) {
        'visit' => Visit::factory()->create([
            'route_stop_id' => null, 'customer_id' => $customer->id, 'driver_id' => $driver->id, 'arrived_at' => "{$day} 10:00:00",
        ]),
        'sale' => Sale::factory()->create([
            'customer_id' => $customer->id, 'created_by' => $driver->user_id, 'date' => "{$day} 10:00:00",
        ]),
        'payment' => Payment::factory()->create([
            'customer_id' => $customer->id, 'received_by' => $driver->user_id, 'paid_at' => "{$day} 10:00:00",
        ]),
        'container_movement' => ContainerMovement::factory()->create([
            'customer_id' => $customer->id, 'created_by' => $driver->user_id, 'created_at' => "{$day} 10:00:00",
        ]),
        'customer' => Customer::factory()->create([
            'driver_id' => $driver->id, 'name' => $customer->name, 'updated_at' => "{$day} 10:00:00",
        ]),
        'route_stop' => RouteStop::factory()->create([
            'route_id' => Route::query()->firstOrCreate(
                ['assigned_driver_id' => $driver->id, 'date' => $day],
                ['status' => 'IN_PROGRESS'],
            )->id,
            'customer_id' => $customer->id,
            'address_id' => CustomerAddress::factory()->create(['customer_id' => $customer->id])->id,
        ]),
    };

    $record->timestamps = false;
    $record->forceFill($review)->save();

    return $record;
}

test('the review inbox lists what is waiting for review with type, driver, customer, date and reason', function (): void {
    $who = inboxDriver();
    foreach (['visit', 'sale', 'payment', 'route_stop', 'customer', 'container_movement'] as $type) {
        flaggedRecord($type, $who);
    }
    Visit::factory()->create(['route_stop_id' => null, 'customer_id' => $who['customer']->id, 'driver_id' => $who['driver']->id]);

    $this->get('/review')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Review/Index')
        ->has('items.data', 6)
        ->where('items.data', fn ($items) => collect($items)->pluck('type')->sort()->values()->all() === ['container_movement', 'customer', 'payment', 'route_stop', 'sale', 'visit'])
        ->where('items.data.0.driver', 'Repartidor Uno')
        ->where('items.data.0.customer', 'Cliente Uno')
        ->where('items.data.0.occurred_at', fn ($date) => str_starts_with((string) $date, '2026-10-22'))
        ->where('items.data.0.reason', fn ($reason) => str_starts_with((string) $reason, 'Reason for ')));
});

test('the review inbox is only for authenticated users', function (): void {
    auth()->logout();

    $this->get('/review')->assertRedirect('/login');
});

/**
 * @return array<int, array<string, mixed>>
 */
function inboxRows(string $query = ''): array
{
    $page = null;
    test()->get('/review'.$query)->assertOk()->assertInertia(function (Assert $inertia) use (&$page): void {
        $page = $inertia->toArray()['props'];
    });

    return $page['items']['data'];
}

test('the review inbox filters by driver', function (): void {
    $one = inboxDriver('Repartidor Uno', 'Cliente Uno');
    $two = inboxDriver('Repartidor Dos', 'Cliente Dos');
    flaggedRecord('sale', $one);
    flaggedRecord('sale', $two);

    $rows = inboxRows('?driver_id='.$two['driver']->id);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['driver'])->toBe('Repartidor Dos');
});

test('the review inbox filters by date range, both ends included', function (): void {
    $who = inboxDriver();
    foreach (['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22'] as $day) {
        flaggedRecord('sale', $who, $day);
    }

    $rows = inboxRows('?from=2026-10-20&to=2026-10-21');

    expect(collect($rows)->map(fn (array $row): string => substr((string) $row['occurred_at'], 0, 10))->sort()->values()->all())
        ->toBe(['2026-10-20', '2026-10-21']);
});

test('the review inbox filters by type', function (): void {
    $who = inboxDriver();
    foreach (['visit', 'sale', 'payment', 'route_stop', 'customer', 'container_movement'] as $type) {
        flaggedRecord($type, $who);
    }

    $rows = inboxRows('?type=container_movement');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe('container_movement');
});

test('the review inbox filters by status and shows what is pending by default', function (): void {
    $who = inboxDriver();
    $pending = flaggedRecord('sale', $who);
    $reviewed = flaggedRecord('payment', $who, review: ['reviewed_at' => '2026-10-23 09:00:00']);

    expect(collect(inboxRows())->pluck('id', 'type')->all())->toBe(['sale' => $pending->id])
        ->and(collect(inboxRows('?status=reviewed'))->pluck('id', 'type')->all())->toBe(['payment' => $reviewed->id])
        ->and(inboxRows('?status=all'))->toHaveCount(2);
});

test('the review inbox paginates fifteen per page and keeps the filters in the page links', function (): void {
    $who = inboxDriver();
    foreach (range(1, 16) as $day) {
        flaggedRecord('sale', $who, sprintf('2026-10-%02d', $day));
    }

    $this->get('/review?type=sale')->assertInertia(fn (Assert $page) => $page
        ->has('items.data', 15)
        ->where('items.total', 16)
        ->where('items.last_page', 2)
        ->where('items.next_page_url', fn ($url) => str_contains((string) $url, 'type=sale')));

    $this->get('/review?type=sale&page=2')->assertInertia(fn (Assert $page) => $page->has('items.data', 1));
});

test('marking a :type as reviewed saves who and when and takes it out of the pending filter', function (string $type): void {
    $who = inboxDriver();
    $record = flaggedRecord($type, $who);
    $other = flaggedRecord($type, $who);

    $this->patch("/review/{$type}/{$record->id}")->assertRedirect();

    $record->refresh();

    expect($record->reviewed_at->toIso8601String())->toBe('2026-10-23T12:00:00+00:00')
        ->and($record->reviewed_by)->toBe($this->admin->id)
        ->and($record->needs_review)->toBeTrue()
        ->and($other->fresh()->reviewed_at)->toBeNull()
        ->and(collect(inboxRows())->pluck('id')->all())->toBe([$other->id])
        ->and(collect(inboxRows('?status=reviewed'))->pluck('reviewed_by')->all())->toBe([$this->admin->name]);
})->with(['visit', 'sale', 'payment', 'route_stop', 'customer', 'container_movement']);

test('the review inbox offers no way to edit or annul a record', function (): void {
    $record = flaggedRecord('sale', inboxDriver());

    $this->put("/review/sale/{$record->id}")->assertStatus(405);
    $this->delete("/review/sale/{$record->id}")->assertStatus(405);

    expect(Sale::query()->find($record->id))->not->toBeNull();
});

test('only a record that is waiting for review can be marked as reviewed', function (): void {
    $who = inboxDriver();
    $unflagged = Sale::factory()->create(['customer_id' => $who['customer']->id]);

    $this->patch("/review/sale/{$unflagged->id}")->assertNotFound();
    $this->patch('/review/sale/999999')->assertNotFound();
    $this->patch("/review/invoice/{$unflagged->id}")->assertNotFound();

    expect($unflagged->fresh()->reviewed_at)->toBeNull();
});

test('the review inbox shows the known review reasons in the language of the panel', function (): void {
    $who = inboxDriver();
    flaggedRecord('sale', $who, review: ['review_reason' => ReviewReason::ForeignCustomer->value]);
    flaggedRecord('route_stop', $who, review: ['review_reason' => ReviewReason::UnvisitedRescheduledStop->value]);
    flaggedRecord('visit', $who, review: ['review_reason' => 'Motivo libre']);

    expect(collect(inboxRows())->pluck('reason', 'type')->all())->toEqual([
        'route_stop' => 'La parada reprogramada terminó el día sin visita.',
        'sale' => 'El cliente está asignado a otro repartidor.',
        'visit' => 'Motivo libre',
    ]);
});

test('a record that was already reviewed keeps its first reviewer and date', function (): void {
    $record = flaggedRecord('sale', inboxDriver());

    $this->patch("/review/sale/{$record->id}")->assertRedirect();
    $this->travelTo('2026-10-24 09:00:00');
    $this->actingAs(User::factory()->superUser()->create());
    $this->patch("/review/sale/{$record->id}")->assertNotFound();

    expect($record->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($record->fresh()->reviewed_at->toIso8601String())->toBe('2026-10-23T12:00:00+00:00');
});

test('a customer of another driver edited through the push shows up in the inbox', function (): void {
    $owner = inboxDriver('Repartidor Dueño', 'Cliente Ajeno');
    $editor = inboxDriver('Repartidor Editor', 'Otro');
    Sanctum::actingAs($editor['driver']->user, ['*']);

    push(['customers' => [[
        'uuid' => (string) Str::uuid(),
        'server_id' => $owner['customer']->id,
        'name' => 'Cliente Ajeno',
        'modified_at' => '2026-10-23T11:00:00Z',
        'address' => ['street' => 'Av Belgrano 100'],
    ]]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $this->actingAs($this->admin, 'web');
    $rows = inboxRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe('customer')
        ->and($rows[0]['driver'])->toBe('Repartidor Dueño')
        ->and($rows[0]['reason'])->toBe('El cliente está asignado a otro repartidor.');
});

test('only a super user can open the review inbox or mark records as reviewed', function (): void {
    $record = flaggedRecord('sale', inboxDriver());
    $this->actingAs(User::factory()->create(['is_super_user' => false]), 'web');

    $this->get('/review')->assertForbidden();
    $this->patch("/review/sale/{$record->id}")->assertForbidden();

    expect($record->fresh()->reviewed_at)->toBeNull();
});
