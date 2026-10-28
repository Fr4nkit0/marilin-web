<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Sanctum\PersonalAccessToken;

test('a module model finds its factory inside its own module', function (): void {
    expect(Factory::resolveFactoryName('App\\Street\\Sales\\Models\\Sale'))->toBe('App\\Street\\Sales\\Database\\Factories\\SaleFactory')
        ->and(Factory::resolveFactoryName('App\\Catalog\\Models\\Product'))->toBe('App\\Catalog\\Database\\Factories\\ProductFactory');
});

test('a model still in app/Models keeps its factory in database/factories', function (): void {
    expect(Factory::resolveFactoryName(User::class))->toBe('Database\\Factories\\UserFactory');
});

test('tokens store the user alias, not its class name', function (): void {
    User::factory()->create()->createToken('app');

    expect(PersonalAccessToken::query()->sole()->tokenable_type)->toBe('user');
});
