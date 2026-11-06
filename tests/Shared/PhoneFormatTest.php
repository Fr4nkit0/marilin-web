<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->superUser()->create();
});

dataset('typed phones', [
    'international with dashes' => ['+54 387 421-1001', '387 421-1001'],
    'international digits only' => ['+543874239999', '387 423-9999'],
    'international mobile 9' => ['+54 9 387 587-4823', '387 587-4823'],
    'trunk zero' => ['0387 4211001', '387 421-1001'],
    'dashes' => ['387-587-4823', '387 587-4823'],
    'already formatted' => ['387 587-4823', '387 587-4823'],
    'plain ten digits' => ['3875874823', '387 587-4823'],
]);

test('the web stores a new customer phone as 387 XXX-XXXX', function (string $typed, string $stored): void {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'phone_number' => $typed])
        ->assertRedirect(route('customers.index'));

    expect(Customer::query()->where('name', 'Kiosco Ana')->firstOrFail()->phone_number)->toBe($stored);
})->with('typed phones');

test('the web stores an edited customer phone as 387 XXX-XXXX', function (): void {
    $customer = Customer::factory()->create(['phone_number' => '387 400-0000']);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true]);

    $this->actingAs($this->admin)
        ->put(route('customers.update', $customer), ['name' => $customer->name, 'street' => 'Mitre 50', 'phone_number' => '+54 387 421-1001'])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->phone_number)->toBe('387 421-1001');
});

test('a phone that is not ten digits after cleaning is kept as typed, trimmed', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'phone_number' => ' 421-1001 ']);

    expect(Customer::query()->where('name', 'Kiosco Ana')->firstOrFail()->phone_number)->toBe('421-1001');
});

test('an empty phone stays empty', function (): void {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'phone_number' => '']);

    expect(Customer::query()->where('name', 'Kiosco Ana')->firstOrFail()->phone_number)->toBeNull();
});

test('a customer created and edited from the app is stored as 387 XXX-XXXX', function (): void {
    $user = User::factory()->create(['enabled' => true]);
    Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);
    Sanctum::actingAs($user, ['*']);
    $uuid = (string) Str::uuid();

    $this->postJson('/api/v1/sync/push', ['customers' => [[
        'uuid' => $uuid,
        'name' => 'Almacen Don Pepe',
        'phone_number' => '+543874550101',
        'modified_at' => '2026-10-20T09:00:00-03:00',
        'address' => ['street' => 'Av Belgrano 100'],
    ]]])->assertOk()->assertJsonPath('data.customers.0.status', 'accepted');

    $customer = Customer::query()->where('client_uuid', $uuid)->firstOrFail();
    expect($customer->phone_number)->toBe('387 455-0101');

    $this->postJson('/api/v1/sync/push', ['customers' => [[
        'uuid' => $uuid,
        'server_id' => $customer->id,
        'phone_number' => '0387-4239999',
        'modified_at' => now()->addMinute()->toIso8601String(),
    ]]])->assertOk();

    expect($customer->fresh()->phone_number)->toBe('387 423-9999');
});

test('a driver account phone is stored as 387 XXX-XXXX', function (): void {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Juan Pérez',
            'username' => 'juanp',
            'phone_number' => '+54 387 421-1001',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])
        ->assertSessionHasNoErrors();

    expect(User::query()->where('username', 'juanp')->firstOrFail()->phone_number)->toBe('387 421-1001');
});
