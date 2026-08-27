<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPreference;
use App\Models\Repartidor;
use App\Models\User;

test('user model can be created with factory and has correct casts', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'is_super_user' => true,
        'enabled' => true,
    ]);

    expect($user->username)->toBe('testuser');
    expect($user->is_super_user)->toBeTrue();
    expect($user->enabled)->toBeTrue();
});

test('repartidor belongs to a user and has customers and routes', function () {
    $user = User::factory()->create();
    $repartidor = Repartidor::factory()->create(['user_id' => $user->id]);

    expect($repartidor->user->id)->toBe($user->id);
    expect($user->repartidor->id)->toBe($repartidor->id);

    $customer = Customer::factory()->create(['repartidor_id' => $repartidor->id]);
    expect($repartidor->customers)->toHaveCount(1);
    expect($repartidor->customers->first()->id)->toBe($customer->id);
});

test('customer has addresses, primary address, and preferences', function () {
    $customer = Customer::factory()->create();

    $primaryAddress = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_primary' => true,
    ]);

    $secondaryAddress = CustomerAddress::factory()->secondary()->create([
        'customer_id' => $customer->id,
    ]);

    $preference = CustomerPreference::factory()->create([
        'customer_id' => $customer->id,
        'day_of_week' => 2,
    ]);

    $customer->refresh();

    expect($customer->addresses)->toHaveCount(2);
    expect($customer->primaryAddress->id)->toBe($primaryAddress->id);
    expect($customer->preferences)->toHaveCount(1);
    expect($customer->preferences->first()->day_of_week)->toBe(2);

    expect(CustomerAddress::primary()->count())->toBe(1);
    expect(CustomerPreference::day(2)->count())->toBe(1);
});

test('customer belongs to creator user', function () {
    $creator = User::factory()->create();
    $customer = Customer::factory()->create(['created_by' => $creator->id]);

    expect($customer->creator->id)->toBe($creator->id);
    expect($creator->customersCreated)->toHaveCount(1);
});
