<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['enabled' => true]);
});

test('creating a customer from the web stores the floor as text', function (): void {
    $this->actingAs($this->user)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'floor' => 'PB'])
        ->assertRedirect(route('customers.index'));

    $customer = Customer::query()->where('name', 'Kiosco Ana')->firstOrFail();
    expect($customer->primaryAddress->floor)->toBe('PB');
});

test('editing a customer from the web updates the floor as text', function (): void {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'floor' => '3', 'is_primary' => true]);

    $this->actingAs($this->user)
        ->put(route('customers.update', $customer), ['name' => $customer->name, 'street' => 'Mitre 50', 'floor' => 'Local 2'])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->primaryAddress->floor)->toBe('Local 2');
});

test('the web rejects a floor longer than 20 characters', function (): void {
    $this->actingAs($this->user)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'floor' => str_repeat('A', 21)])
        ->assertSessionHasErrors('floor');

    expect(Customer::query()->where('name', 'Kiosco Ana')->exists())->toBeFalse();
});

test('creating a customer stores a catalog neighborhood by its exact name', function (): void {
    Neighborhood::factory()->create(['name' => 'Villa San Lorenzo']);

    $this->actingAs($this->user)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'neighborhood' => 'Villa San Lorenzo'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.index'));

    expect(Customer::query()->where('name', 'Kiosco Ana')->sole()->primaryAddress->neighborhood)->toBe('Villa San Lorenzo');
});

test('creating a customer without neighborhood leaves it empty', function (): void {
    $this->actingAs($this->user)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'neighborhood' => ''])
        ->assertSessionHasNoErrors();

    expect(Customer::query()->where('name', 'Kiosco Ana')->sole()->primaryAddress->neighborhood)->toBeNull();
});

test('the web rejects a neighborhood outside the catalog or disabled and stores nothing', function (string $name): void {
    Neighborhood::factory()->create(['name' => 'Villa San Lorenzo']);
    Neighborhood::factory()->create(['name' => 'Barrio Cerrado', 'enabled' => false]);

    $this->actingAs($this->user)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'neighborhood' => $name])
        ->assertSessionHasErrors('neighborhood');

    expect(Customer::query()->count())->toBe(0)
        ->and(CustomerAddress::query()->count())->toBe(0);
})->with(['outside the catalog' => 'Alto Comedero', 'disabled' => 'Barrio Cerrado', 'disabled in another case' => 'barrio cerrado']);

test('creating a customer stores the catalog spelling of a neighborhood typed in any case or without accents', function (string $typed, string $stored): void {
    Neighborhood::factory()->create(['name' => 'Tres Cerritos']);
    Neighborhood::factory()->create(['name' => 'Castañares Grupo 200']);
    Neighborhood::factory()->create(['name' => 'Villa San Lorenzo']);

    $this->actingAs($this->user)
        ->post(route('customers.store'), ['name' => 'Kiosco Ana', 'street' => 'Mitre 50', 'neighborhood' => $typed])
        ->assertSessionHasNoErrors();

    expect(Customer::query()->where('name', 'Kiosco Ana')->sole()->primaryAddress->neighborhood)->toBe($stored);
})->with([
    'lower case' => ['tres cerritos', 'Tres Cerritos'],
    'without the accent' => ['castanares grupo 200', 'Castañares Grupo 200'],
    'capitals and spaces around' => ['  VILLA SAN LORENZO ', 'Villa San Lorenzo'],
]);

test('editing a customer stores the catalog spelling of a neighborhood typed without accents', function (): void {
    Neighborhood::factory()->create(['name' => 'Castañares Grupo 200']);
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'neighborhood' => 'Centro']);

    $this->actingAs($this->user)
        ->put(route('customers.update', $customer), ['name' => $customer->name, 'street' => 'Mitre 50', 'neighborhood' => 'CASTANARES grupo 200'])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->primaryAddress->neighborhood)->toBe('Castañares Grupo 200');
});

test('editing a customer changes its neighborhood and keeps the coordinates', function (): void {
    Neighborhood::factory()->create(['name' => 'Tres Cerritos']);
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'neighborhood' => 'Centro', 'latitude' => -24.78, 'longitude' => -65.41]);

    $this->actingAs($this->user)
        ->put(route('customers.update', $customer), ['name' => $customer->name, 'street' => 'Mitre 50', 'neighborhood' => 'Tres Cerritos'])
        ->assertSessionHasNoErrors();

    $address = $customer->fresh()->primaryAddress;
    expect($address->neighborhood)->toBe('Tres Cerritos')
        ->and((float) $address->latitude)->toBe(-24.78)
        ->and((float) $address->longitude)->toBe(-65.41);
});

test('editing a customer with an empty neighborhood clears it', function (): void {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'neighborhood' => 'Centro']);

    $this->actingAs($this->user)
        ->put(route('customers.update', $customer), ['name' => $customer->name, 'street' => 'Mitre 50', 'neighborhood' => ''])
        ->assertSessionHasNoErrors();

    expect($customer->fresh()->primaryAddress->neighborhood)->toBeNull();
});

test('editing rejects a neighborhood outside the catalog and keeps the stored one', function (): void {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'neighborhood' => 'Centro']);

    $this->actingAs($this->user)
        ->put(route('customers.update', $customer), ['name' => 'Otro', 'street' => 'Mitre 50', 'neighborhood' => 'Alto Comedero'])
        ->assertSessionHasErrors('neighborhood');

    expect($customer->fresh()->name)->not->toBe('Otro')
        ->and($customer->fresh()->primaryAddress->neighborhood)->toBe('Centro');
});

test('the customers list offers the enabled neighborhoods and container types by name', function (): void {
    Neighborhood::factory()->create(['name' => 'Tres Cerritos']);
    Neighborhood::factory()->create(['name' => 'Castañares']);
    Neighborhood::factory()->create(['name' => 'Barrio Cerrado', 'enabled' => false]);
    $sifon = ContainerType::factory()->create(['description' => 'Sifón 1.5L']);
    $bidon = ContainerType::factory()->create(['description' => 'Bidón 20L']);
    ContainerType::factory()->create(['description' => 'Damajuana', 'enabled' => false]);

    $this->actingAs($this->user)
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Customers/Index')
            ->where('neighborhoods', ['Castañares', 'Tres Cerritos'])
            ->where('containerTypes', [
                ['id' => $bidon->id, 'description' => 'Bidón 20L'],
                ['id' => $sifon->id, 'description' => 'Sifón 1.5L'],
            ]));
});

test('the customer page carries the neighborhood of the primary address', function (): void {
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_primary' => true, 'neighborhood' => 'Tres Cerritos']);

    $this->actingAs($this->user)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Customers/Show')
            ->where('customer.primary_address.neighborhood', 'Tres Cerritos'));
});
