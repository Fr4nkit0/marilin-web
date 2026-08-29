<?php

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPreference;
use App\Models\Driver;
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

test('customer belongs to driver and creator', function () {
    $user = User::factory()->create();
    $driver = Driver::factory()->create();

    $customer = Customer::factory()->create([
        'driver_id' => $driver->id,
        'created_by' => $user->id,
    ]);

    expect($customer->driver)->toBeInstanceOf(Driver::class)
        ->and($customer->driver->id)->toBe($driver->id)
        ->and($customer->creator)->toBeInstanceOf(User::class);
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
