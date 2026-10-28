<?php

declare(strict_types=1);

use App\Accounts\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
