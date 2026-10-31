<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

require_once __DIR__.'/PushHelpers.php';

beforeEach(function (): void {
    $this->travelTo('2026-10-22 12:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    $this->customer = Customer::factory()->create(['driver_id' => $this->driver->id, 'updated_at' => '2026-10-20 10:00:00']);
    Sanctum::actingAs($this->user, ['*']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function preferencePayload(Customer $customer, array $overrides = []): array
{
    return array_replace([
        'uuid' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'day_of_week' => 1,
        'time_window_start' => '09:00',
        'time_window_end' => '12:00',
        'enabled' => true,
        'modified_at' => '2026-10-21T09:00:00-03:00',
    ], $overrides);
}

function serverPreference(Customer $customer, ?string $uuid = null, int $day = 1): CustomerPreference
{
    return CustomerPreference::factory()->create([
        'customer_id' => $customer->id,
        'client_uuid' => $uuid,
        'day_of_week' => $day,
        'time_window_start' => '09:00:00',
        'time_window_end' => '12:00:00',
        'updated_at' => '2026-10-20 10:00:00',
    ]);
}

/**
 * @return array<string, mixed>
 */
function preferenceState(CustomerPreference $preference): array
{
    return $preference->fresh()->only(['customer_id', 'day_of_week', 'time_window_start', 'time_window_end', 'enabled', 'client_uuid', 'updated_at']);
}

test('push creates a preference with the app modification date and the moment it was received', function (): void {
    $payload = preferencePayload($this->customer);

    $response = push(['customer_preferences' => [$payload]]);

    $stored = CustomerPreference::query()->where('client_uuid', $payload['uuid'])->firstOrFail();

    $response->assertOk()
        ->assertJsonPath('data.customer_preferences.0.uuid', $payload['uuid'])
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $stored->id)
        ->assertJsonPath('data.customer_preferences.0.review_reasons', []);

    expect($stored->customer_id)->toBe($this->customer->id)
        ->and($stored->day_of_week)->toBe(1)
        ->and($stored->time_window_start)->toBe('09:00:00')
        ->and($stored->time_window_end)->toBe('12:00:00')
        ->and($stored->enabled)->toBeTrue()
        ->and($stored->created_by)->toBe($this->user->id)
        ->and($stored->updated_at->toIso8601String())->toBe('2026-10-21T09:00:00-03:00')
        ->and($stored->received_at->toIso8601String())->toBe('2026-10-22T12:00:00-03:00');
});

test('push creates a preference without a time window', function (): void {
    $payload = preferencePayload($this->customer, ['time_window_start' => null, 'time_window_end' => null]);

    push(['customer_preferences' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted');

    $stored = CustomerPreference::query()->where('client_uuid', $payload['uuid'])->firstOrFail();
    expect($stored->time_window_start)->toBeNull()
        ->and($stored->time_window_end)->toBeNull();
});

test('push resolves the customer of a preference by the uuid of a customer sent in the same batch', function (): void {
    $customerUuid = (string) Str::uuid();
    $payload = preferencePayload($this->customer, ['customer_id' => null, 'customer_uuid' => $customerUuid]);

    push([
        'customers' => [[
            'uuid' => $customerUuid,
            'name' => 'Kiosco Nuevo',
            'modified_at' => '2026-10-21T08:00:00-03:00',
            'address' => ['street' => 'Caseros 500'],
        ]],
        'customer_preferences' => [$payload],
    ])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted');

    $customer = Customer::query()->where('client_uuid', $customerUuid)->firstOrFail();
    expect(CustomerPreference::query()->where('client_uuid', $payload['uuid'])->value('customer_id'))->toBe($customer->id);
});

test('push applies a newer edit found by uuid', function (): void {
    $uuid = (string) Str::uuid();
    $preference = serverPreference($this->customer, $uuid);

    push(['customer_preferences' => [preferencePayload($this->customer, [
        'uuid' => $uuid,
        'day_of_week' => 3,
        'time_window_start' => '14:00',
        'time_window_end' => '18:30',
    ])]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $preference->id);

    $preference->refresh();
    expect($preference->day_of_week)->toBe(3)
        ->and($preference->time_window_start)->toBe('14:00:00')
        ->and($preference->time_window_end)->toBe('18:30:00')
        ->and($preference->last_modified_by)->toBe($this->user->id)
        ->and($preference->updated_at->toIso8601String())->toBe('2026-10-21T09:00:00-03:00')
        ->and($preference->received_at->toIso8601String())->toBe('2026-10-22T12:00:00-03:00')
        ->and(CustomerPreference::query()->count())->toBe(1);
});

test('push applies a newer edit found by server id and adopts the app uuid', function (): void {
    $preference = serverPreference($this->customer);
    $uuid = (string) Str::uuid();

    push(['customer_preferences' => [preferencePayload($this->customer, [
        'uuid' => $uuid,
        'server_id' => $preference->id,
        'time_window_end' => '13:00',
    ])]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $preference->id);

    $preference->refresh();
    expect($preference->time_window_end)->toBe('13:00:00')
        ->and($preference->client_uuid)->toBe($uuid)
        ->and(CustomerPreference::query()->count())->toBe(1);
});

test('push ignores an edit older than the server version but still accepts it', function (): void {
    $uuid = (string) Str::uuid();
    $preference = serverPreference($this->customer, $uuid);
    $before = preferenceState($preference);

    push(['customer_preferences' => [preferencePayload($this->customer, [
        'uuid' => $uuid,
        'day_of_week' => 5,
        'modified_at' => '2026-10-20T09:00:00-03:00',
    ])]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $preference->id);

    expect(preferenceState($preference))->toEqual($before);
});

test('push answers a replayed preference with the same server id and creates nothing', function (): void {
    $payload = ['customer_preferences' => [preferencePayload($this->customer)]];

    $first = push($payload);
    $second = push($payload);

    $second->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $first->json('data.customer_preferences.0.server_id'));

    expect(CustomerPreference::query()->count())->toBe(1);
});

test('push rejects a preference that points at a server id that does not exist', function (): void {
    $payload = preferencePayload($this->customer, ['server_id' => 999999]);

    push(['customer_preferences' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.server_id', null)
        ->assertJsonPath('data.customer_preferences.0.error', 'The server_id does not match any customer preference.');

    expect(CustomerPreference::query()->count())->toBe(0);
});

test('push rejects a preference whose customer reference does not match any customer', function (): void {
    push(['customer_preferences' => [preferencePayload($this->customer, ['customer_id' => 999999])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The customer reference does not match any record.');

    expect(CustomerPreference::query()->count())->toBe(0);
});

test('push rejects moving a preference to another customer', function (): void {
    $other = Customer::factory()->create(['driver_id' => $this->driver->id]);
    $uuid = (string) Str::uuid();
    $preference = serverPreference($this->customer, $uuid);
    $before = preferenceState($preference);

    push(['customer_preferences' => [preferencePayload($other, ['uuid' => $uuid])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.server_id', null)
        ->assertJsonPath('data.customer_preferences.0.error', 'The record belongs to another customer.');

    expect(preferenceState($preference))->toEqual($before);
});

test('push rejects an invalid preference with a readable reason and still accepts the rest of the batch', function (): void {
    $invalid = preferencePayload($this->customer, ['time_window_start' => '9am']);
    $valid = preferencePayload($this->customer, ['day_of_week' => 2]);

    $response = push(['customer_preferences' => [$invalid, $valid]]);

    $response->assertOk()
        ->assertJsonPath('data.customer_preferences.0.uuid', $invalid['uuid'])
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.1.status', 'accepted');

    expect($response->json('data.customer_preferences.0.error'))->toBe('El campo desde debe tener el formato H:i.')
        ->and(CustomerPreference::query()->where('client_uuid', $invalid['uuid'])->exists())->toBeFalse()
        ->and(CustomerPreference::query()->where('client_uuid', $valid['uuid'])->exists())->toBeTrue();
});

test('push rejects a day of week outside 0 to 6', function (int $day): void {
    $response = push(['customer_preferences' => [preferencePayload($this->customer, ['day_of_week' => $day])]]);

    $response->assertOk()->assertJsonPath('data.customer_preferences.0.status', 'rejected');
    expect($response->json('data.customer_preferences.0.error'))->toBe('El campo día debe estar entre 0 y 6.')
        ->and(CustomerPreference::query()->count())->toBe(0);
})->with([-1, 7]);

test('push accepts the first and last day of the week', function (int $day): void {
    push(['customer_preferences' => [preferencePayload($this->customer, ['day_of_week' => $day])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted');
})->with([0, 6]);

test('push rejects a time window that ends before it starts', function (string $end): void {
    $payload = preferencePayload($this->customer, ['time_window_start' => '12:00', 'time_window_end' => $end]);

    push(['customer_preferences' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The time window end must be after its start.');

    expect(CustomerPreference::query()->count())->toBe(0);
})->with(['09:00', '12:00']);

test('push rejects a second preference for the same customer, day and start', function (): void {
    $existing = serverPreference($this->customer);
    $before = preferenceState($existing);

    push(['customer_preferences' => [preferencePayload($this->customer, ['time_window_end' => '11:00'])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The customer already has a preference for that day and time.');

    expect(CustomerPreference::query()->count())->toBe(1)
        ->and(preferenceState($existing))->toEqual($before);
});

test('push rejects an edit that moves a preference onto the day and start of another one', function (): void {
    serverPreference($this->customer, null, 1);
    $uuid = (string) Str::uuid();
    $moved = serverPreference($this->customer, $uuid, 2);
    $before = preferenceState($moved);

    push(['customer_preferences' => [preferencePayload($this->customer, ['uuid' => $uuid, 'day_of_week' => 1])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The customer already has a preference for that day and time.');

    expect(preferenceState($moved))->toEqual($before);
});

test('push rejects a window that overlaps another enabled one of the same day', function (?string $start, ?string $end, string $message): void {
    CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => $start, 'time_window_end' => $end]);
    $payload = preferencePayload($this->customer, ['time_window_start' => '12:45', 'time_window_end' => '13:30']);

    push(['customer_preferences' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.server_id', null)
        ->assertJsonPath('data.customer_preferences.0.error', $message);

    expect(CustomerPreference::query()->count())->toBe(1)
        ->and(CustomerPreference::query()->where('client_uuid', $payload['uuid'])->exists())->toBeFalse();
})->with([
    'a full window' => ['08:30:00', '13:00:00', 'Se superpone con 08:30–13:00.'],
    'a window with only a start' => ['12:30:00', null, 'Se superpone con desde 12:30.'],
    'a window with only an end' => [null, '13:00:00', 'Se superpone con hasta 13:00.'],
    'a whole-day window' => [null, null, 'Se superpone con todo el día.'],
]);

test('push accepts windows that only touch, and windows over a removed preference', function (): void {
    CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '08:30:00', 'time_window_end' => '13:00:00']);
    CustomerPreference::factory()->disabled()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '14:00:00', 'time_window_end' => '18:00:00']);

    push(['customer_preferences' => [
        preferencePayload($this->customer, ['time_window_start' => '13:00', 'time_window_end' => '15:00']),
        preferencePayload($this->customer, ['time_window_start' => '07:00', 'time_window_end' => '08:30']),
    ]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.1.status', 'accepted');

    expect(CustomerPreference::query()->where('enabled', true)->count())->toBe(3);
});

test('push answers the taken day and start before the overlap', function (): void {
    CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '08:30:00', 'time_window_end' => '13:00:00']);

    push(['customer_preferences' => [preferencePayload($this->customer, ['time_window_start' => '08:30', 'time_window_end' => '10:00'])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'rejected')
        ->assertJsonPath('data.customer_preferences.0.error', 'The customer already has a preference for that day and time.');
});

test('push edits a preference without overlapping with itself, and removes one even when it overlaps', function (): void {
    $uuid = (string) Str::uuid();
    $preference = serverPreference($this->customer, $uuid);
    $overlapping = CustomerPreference::factory()->create([
        'customer_id' => $this->customer->id, 'client_uuid' => (string) Str::uuid(), 'day_of_week' => 1,
        'time_window_start' => '10:00:00', 'time_window_end' => '11:00:00', 'updated_at' => '2026-10-20 10:00:00',
    ]);

    push(['customer_preferences' => [
        preferencePayload($this->customer, ['uuid' => $overlapping->client_uuid, 'time_window_start' => '10:00', 'time_window_end' => '11:00', 'enabled' => false]),
        preferencePayload($this->customer, ['uuid' => $uuid, 'time_window_start' => '09:00', 'time_window_end' => '13:00']),
    ]])->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.1.status', 'accepted');

    expect($overlapping->fresh()->enabled)->toBeFalse()
        ->and($preference->fresh()->time_window_end)->toBe('13:00:00');
});

test('push leaves a preference on a customer of another driver pending for the admin (S3)', function (): void {
    $otherDriver = Driver::factory()->create(['enabled' => true]);
    $foreign = Customer::factory()->create(['driver_id' => $otherDriver->id]);
    $payload = preferencePayload($foreign);

    push(['customer_preferences' => [$payload]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.review_reasons', ['Customer is assigned to another driver.']);

    $foreign->refresh();
    expect(CustomerPreference::query()->where('client_uuid', $payload['uuid'])->exists())->toBeFalse()
        ->and($foreign->needs_review)->toBeFalse()
        ->and($foreign->driver_id)->toBe($otherDriver->id);
});

test('push removes a preference when the app sends enabled false', function (): void {
    $uuid = (string) Str::uuid();
    $preference = serverPreference($this->customer, $uuid);

    push(['customer_preferences' => [preferencePayload($this->customer, ['uuid' => $uuid, 'enabled' => false])]])
        ->assertOk()
        ->assertJsonPath('data.customer_preferences.0.status', 'accepted')
        ->assertJsonPath('data.customer_preferences.0.server_id', $preference->id);

    expect($preference->fresh()->enabled)->toBeFalse();
});

test('pull returns a pushed preference by the moment it was received even when its app date is older than the cursor', function (): void {
    $payload = preferencePayload($this->customer, ['modified_at' => '2026-10-20T09:00:00-03:00']);
    $id = push(['customer_preferences' => [$payload]])->json('data.customer_preferences.0.server_id');
    $stale = serverPreference($this->customer, null, 4);

    $this->travel(1)->minutes();
    $cursor = now()->subHour()->toIso8601String();
    $response = $this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk();

    $rows = collect($response->json('data.customer_preferences'));
    expect($rows->pluck('id')->all())->toBe([$id])
        ->and($rows->first()['client_uuid'])->toBe($payload['uuid'])
        ->and($rows->pluck('id'))->not->toContain($stale->id);
});
