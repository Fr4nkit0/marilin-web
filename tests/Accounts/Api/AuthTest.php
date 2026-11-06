<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('active driver can login with the username and receive sanctum token', function (): void {
    $user = User::factory()->create([
        'email' => null,
        'username' => 'jperez',
        'password' => 'secret123',
        'enabled' => true,
    ]);

    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'jperez',
        'password' => 'secret123',
        'device_name' => 'test-device',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'username',
                    'phone_number',
                    'is_super_user',
                    'enabled',
                    'driver' => [
                        'id',
                        'user_id',
                        'enabled',
                    ],
                ],
            ],
            'message',
        ]);

    expect($response->json('message'))->toBe('Sesión iniciada.')
        ->and($response->json('data.user.username'))->toBe('jperez')
        ->and($response->json('data.user.email'))->toBeNull()
        ->and($user->tokens()->count())->toBe(2);
});

test('the app login finds the username whatever its letter case without changing it', function (): void {
    $user = User::factory()->create(['email' => null, 'username' => 'JPerez', 'password' => 'secret123', 'enabled' => true]);
    Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);

    $this->postJson('/api/v1/auth/login', ['username' => 'jperez', 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.user.username', 'JPerez');

    expect($user->fresh()->username)->toBe('JPerez');
});

test('the app login no longer takes the email', function (): void {
    $user = User::factory()->create(['email' => 'driver@marilin.app', 'username' => 'jperez', 'password' => 'secret123', 'enabled' => true]);
    Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);

    $this->postJson('/api/v1/auth/login', ['email' => 'driver@marilin.app', 'password' => 'secret123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username'])
        ->assertJsonMissingPath('data.token');

    $this->postJson('/api/v1/auth/login', ['username' => 'driver@marilin.app', 'password' => 'secret123'])
        ->assertStatus(422)
        ->assertJsonPath('errors.username.0', __('auth.failed'));

    expect($user->tokens()->count())->toBe(0);
});

test('login fails with invalid credentials', function (): void {
    $user = User::factory()->create([
        'username' => 'jperez',
        'password' => 'secret123',
        'enabled' => true,
    ]);

    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'jperez',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.username.0', __('auth.failed'));

    expect($user->tokens()->count())->toBe(0);
});

test('login fails when user is disabled', function (): void {
    $user = User::factory()->create([
        'username' => 'jperez',
        'password' => 'secret123',
        'enabled' => false,
    ]);

    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'jperez',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username' => 'Tu cuenta está deshabilitada.']);

    expect($user->tokens()->count())->toBe(0);
});

test('login fails when user has no driver profile or driver is disabled', function (): void {
    $user = User::factory()->create([
        'username' => 'nodriver',
        'password' => 'secret123',
        'enabled' => true,
    ]);

    // No driver record
    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'nodriver',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username' => 'El usuario no es un repartidor activo.']);

    // Disabled driver record
    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => false,
    ]);

    $response2 = $this->postJson('/api/v1/auth/login', [
        'username' => 'nodriver',
        'password' => 'secret123',
    ]);

    $response2->assertStatus(422)
        ->assertJsonValidationErrors(['username' => 'El usuario no es un repartidor activo.']);

    expect($user->tokens()->count())->toBe(0);
});

test('authenticated driver can access profile via me endpoint', function (): void {
    $user = User::factory()->create(['enabled' => true]);
    $driver = Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'driver' => [
                    'id' => $driver->id,
                ],
            ],
            'message' => null,
        ]);
});

test('unauthenticated request to me endpoint returns 401', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

test('authenticated user can logout successfully', function (): void {
    $user = User::factory()->create(['enabled' => true]);
    Driver::factory()->create(['user_id' => $user->id, 'enabled' => true]);

    $token = $user->createToken('test-token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJson([
            'data' => null,
            'message' => 'Sesión cerrada.',
        ]);

    expect($user->tokens()->count())->toBe(0);
});
