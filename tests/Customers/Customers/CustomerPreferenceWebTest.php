<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->travelTo('2026-10-22 12:00:00');
    $this->admin = User::factory()->superUser()->create(['name' => 'Admin']);
    $this->customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_primary' => true]);
    $this->actingAs($this->admin);
});

/**
 * @return list<array<string, mixed>>
 */
function storedPreferences(Customer $customer): array
{
    return $customer->preferences()->orderBy('id')->get()
        ->map(fn (CustomerPreference $preference): array => $preference->only(['day_of_week', 'time_window_start', 'time_window_end', 'enabled']))
        ->all();
}

test('the admin adds a delivery preference with a time window', function (): void {
    $this->post(route('customers.preferences.store', $this->customer), ['day_of_week' => 1, 'time_window_start' => '08:00', 'time_window_end' => '12:00'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.show', $this->customer))
        ->assertSessionHas('success', 'Día y horario agregado.');

    $preference = $this->customer->preferences()->sole();
    expect($preference->only(['day_of_week', 'time_window_start', 'time_window_end', 'enabled', 'created_by', 'last_modified_by']))->toBe([
        'day_of_week' => 1,
        'time_window_start' => '08:00:00',
        'time_window_end' => '12:00:00',
        'enabled' => true,
        'created_by' => $this->admin->id,
        'last_modified_by' => null,
    ])
        ->and($preference->created_at->toDateTimeString())->toBe('2026-10-22 12:00:00')
        ->and($preference->received_at)->toBeNull();
});

test('the admin adds a whole-day preference', function (): void {
    $this->post(route('customers.preferences.store', $this->customer), ['day_of_week' => 0, 'time_window_start' => '', 'time_window_end' => ''])
        ->assertSessionHasNoErrors();

    expect(storedPreferences($this->customer))->toBe([
        ['day_of_week' => 0, 'time_window_start' => null, 'time_window_end' => null, 'enabled' => true],
    ]);
});

test('adding again a removed preference brings it back instead of clashing with it', function (): void {
    $removed = CustomerPreference::factory()->disabled()->create(['customer_id' => $this->customer->id, 'day_of_week' => 2, 'time_window_start' => '08:00:00', 'time_window_end' => '10:00:00']);

    $this->post(route('customers.preferences.store', $this->customer), ['day_of_week' => 2, 'time_window_start' => '08:00', 'time_window_end' => '11:00'])
        ->assertSessionHasNoErrors();

    expect($this->customer->preferences()->sole()->only(['id', 'time_window_end', 'enabled', 'last_modified_by']))->toBe([
        'id' => $removed->id,
        'time_window_end' => '11:00:00',
        'enabled' => true,
        'last_modified_by' => $this->admin->id,
    ]);
});

test('the admin changes a preference', function (): void {
    $preference = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1]);

    $this->put(route('customers.preferences.update', [$this->customer, $preference]), ['day_of_week' => 3, 'time_window_start' => '14:00', 'time_window_end' => '18:00'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Día y horario actualizado.');

    expect($preference->fresh()->only(['day_of_week', 'time_window_start', 'time_window_end', 'enabled', 'last_modified_by']))->toBe([
        'day_of_week' => 3,
        'time_window_start' => '14:00:00',
        'time_window_end' => '18:00:00',
        'enabled' => true,
        'last_modified_by' => $this->admin->id,
    ])
        ->and($preference->fresh()->updated_at->toDateTimeString())->toBe('2026-10-22 12:00:00');
});

test('removing a preference disables it and keeps the row for the app', function (): void {
    $preference = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1]);

    $this->delete(route('customers.preferences.destroy', [$this->customer, $preference]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Día y horario quitado.');

    expect($preference->fresh()->only(['day_of_week', 'time_window_start', 'time_window_end', 'enabled', 'last_modified_by']))->toBe([
        'day_of_week' => 1,
        'time_window_start' => '09:00:00',
        'time_window_end' => '12:00:00',
        'enabled' => false,
        'last_modified_by' => $this->admin->id,
    ]);
});

test('a preference of another customer is not found', function (): void {
    $foreign = CustomerPreference::factory()->create();

    $this->put(route('customers.preferences.update', [$this->customer, $foreign]), ['day_of_week' => 3])->assertNotFound();
    $this->delete(route('customers.preferences.destroy', [$this->customer, $foreign]))->assertNotFound();

    expect($foreign->fresh()->enabled)->toBeTrue();
});

test('a window that ends before it starts is rejected on the end field and nothing is stored', function (string $end): void {
    $this->post(route('customers.preferences.store', $this->customer), ['day_of_week' => 1, 'time_window_start' => '12:00', 'time_window_end' => $end])
        ->assertSessionHasErrors(['time_window_end' => 'The time window end must be after its start.']);

    expect($this->customer->preferences()->count())->toBe(0);
})->with(['before' => '08:00', 'equal' => '12:00']);

test('a second preference for the same day and start is rejected on the day field and nothing changes', function (): void {
    $existing = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '09:00:00']);
    $other = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 4, 'time_window_start' => '09:00:00']);

    $this->post(route('customers.preferences.store', $this->customer), ['day_of_week' => 1, 'time_window_start' => '09:00', 'time_window_end' => '11:00'])
        ->assertSessionHasErrors(['day_of_week' => 'The customer already has a preference for that day and time.']);
    $this->put(route('customers.preferences.update', [$this->customer, $other]), ['day_of_week' => 1, 'time_window_start' => '09:00', 'time_window_end' => '11:00'])
        ->assertSessionHasErrors(['day_of_week' => 'The customer already has a preference for that day and time.']);

    expect($this->customer->preferences()->count())->toBe(2)
        ->and($other->fresh()->day_of_week)->toBe(4)
        ->and($existing->fresh()->time_window_end)->toBe('12:00:00');
});

test('the day must be 0 to 6 and the times H:i', function (array $input, string $field): void {
    $this->post(route('customers.preferences.store', $this->customer), $input)->assertSessionHasErrors($field);

    expect($this->customer->preferences()->count())->toBe(0);
})->with([
    'no day' => [['time_window_start' => '08:00'], 'day_of_week'],
    'day 7' => [['day_of_week' => 7], 'day_of_week'],
    'day -1' => [['day_of_week' => -1], 'day_of_week'],
    'start not a time' => [['day_of_week' => 1, 'time_window_start' => '8am'], 'time_window_start'],
    'end not a time' => [['day_of_week' => 1, 'time_window_end' => '25:00'], 'time_window_end'],
]);

test('the customer page lists the enabled preferences only, by day and time', function (): void {
    $monday = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '14:00:00', 'time_window_end' => '18:00:00']);
    $mondayEarly = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '08:00:00', 'time_window_end' => '12:00:00']);
    $sunday = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 0, 'time_window_start' => null, 'time_window_end' => null]);
    CustomerPreference::factory()->disabled()->create(['customer_id' => $this->customer->id, 'day_of_week' => 2]);
    CustomerPreference::factory()->create(['day_of_week' => 3]);

    $this->get(route('customers.show', $this->customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Customers/Show')
            ->where('preferences', [
                ['id' => $sunday->id, 'dayOfWeek' => 0, 'timeWindowStart' => null, 'timeWindowEnd' => null],
                ['id' => $mondayEarly->id, 'dayOfWeek' => 1, 'timeWindowStart' => '08:00', 'timeWindowEnd' => '12:00'],
                ['id' => $monday->id, 'dayOfWeek' => 1, 'timeWindowStart' => '14:00', 'timeWindowEnd' => '18:00'],
            ])
            ->missing('customer.preferences'));
});

test('creating a customer stores its preferences with it', function (): void {
    $this->post(route('customers.store'), [
        'name' => 'Kiosco Ana',
        'street' => 'Mitre 50',
        'preferences' => [
            ['day_of_week' => 1, 'time_window_start' => '08:00', 'time_window_end' => '12:00'],
            ['day_of_week' => 4, 'time_window_start' => '', 'time_window_end' => ''],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('customers.index'));

    $customer = Customer::query()->where('name', 'Kiosco Ana')->sole();
    expect(storedPreferences($customer))->toBe([
        ['day_of_week' => 1, 'time_window_start' => '08:00:00', 'time_window_end' => '12:00:00', 'enabled' => true],
        ['day_of_week' => 4, 'time_window_start' => null, 'time_window_end' => null, 'enabled' => true],
    ])
        ->and($customer->preferences()->pluck('created_by')->unique()->all())->toBe([$this->admin->id]);
});

test('creating a customer with a wrong preference stores neither the customer nor its preferences', function (array $preferences, string $field, string $message): void {
    $this->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'preferences' => $preferences])
        ->assertSessionHasErrors([$field => $message]);

    expect(Customer::query()->where('name', 'Kiosco Ana')->exists())->toBeFalse()
        ->and(CustomerPreference::query()->count())->toBe(0);
})->with([
    'end before start' => [
        [['day_of_week' => 1, 'time_window_start' => '08:00', 'time_window_end' => '12:00'], ['day_of_week' => 2, 'time_window_start' => '12:00', 'time_window_end' => '08:00']],
        'preferences.1.time_window_end',
        'The time window end must be after its start.',
    ],
    'the same day and start twice' => [
        [['day_of_week' => 1, 'time_window_start' => '08:00', 'time_window_end' => '12:00'], ['day_of_week' => 1, 'time_window_start' => '08:00', 'time_window_end' => '10:00']],
        'preferences.1.day_of_week',
        'The customer already has a preference for that day and time.',
    ],
]);

test('editing a customer adds the new preference rows', function (): void {
    $kept = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1]);

    $this->put(route('customers.update', $this->customer), [
        'name' => $this->customer->name,
        'street' => 'Mitre 50',
        'preferences' => [['day_of_week' => 5, 'time_window_start' => '10:00', 'time_window_end' => '']],
    ])->assertSessionHasNoErrors();

    expect(storedPreferences($this->customer))->toBe([
        ['day_of_week' => 1, 'time_window_start' => '09:00:00', 'time_window_end' => '12:00:00', 'enabled' => true],
        ['day_of_week' => 5, 'time_window_start' => '10:00:00', 'time_window_end' => null, 'enabled' => true],
    ])
        ->and($kept->fresh()->enabled)->toBeTrue();
});

test('editing a customer with a clashing preference row changes nothing', function (): void {
    CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 1, 'time_window_start' => '09:00:00']);

    $this->put(route('customers.update', $this->customer), [
        'name' => 'Otro nombre',
        'street' => 'Mitre 50',
        'preferences' => [['day_of_week' => 1, 'time_window_start' => '09:00', 'time_window_end' => '']],
    ])->assertSessionHasErrors(['preferences.0.day_of_week' => 'The customer already has a preference for that day and time.']);

    expect($this->customer->fresh()->name)->not->toBe('Otro nombre')
        ->and($this->customer->preferences()->count())->toBe(1);
});

test('the app pulls the preferences added, changed and removed on the web', function (): void {
    $driverUser = User::factory()->create(['enabled' => true]);
    $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'enabled' => true]);
    $this->customer->update(['driver_id' => $driver->id]);
    $removed = CustomerPreference::factory()->create(['customer_id' => $this->customer->id, 'day_of_week' => 3, 'updated_at' => '2026-10-01 10:00:00']);
    $cursor = now()->subMinute()->toIso8601String();

    $this->post(route('customers.preferences.store', $this->customer), ['day_of_week' => 1, 'time_window_start' => '08:00', 'time_window_end' => '12:00'])
        ->assertSessionHasNoErrors();
    $this->delete(route('customers.preferences.destroy', [$this->customer, $removed]))->assertSessionHasNoErrors();

    Sanctum::actingAs($driverUser, ['*']);
    $pulled = collect($this->getJson('/api/v1/sync/pull?cursor='.urlencode($cursor))->assertOk()->json('data.customer_preferences'));

    expect($pulled->pluck('enabled', 'day_of_week')->sortKeys()->all())->toBe([1 => true, 3 => false]);
});
