<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('active driver can login successfully and receive sanctum token', function (): void {
    $user = User::factory()->create([
        'email' => 'driver@marilin.app',
        'password' => 'secret123',
        'enabled' => true,
    ]);

    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'driver@marilin.app',
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

    expect($response->json('message'))->toBe('Login successful');
    expect($response->json('data.user.email'))->toBe('driver@marilin.app');
});

test('login fails with invalid credentials', function (): void {
    $user = User::factory()->create([
        'email' => 'driver@marilin.app',
        'password' => 'secret123',
        'enabled' => true,
    ]);

    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'driver@marilin.app',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails when user is disabled', function (): void {
    $user = User::factory()->create([
        'email' => 'driver@marilin.app',
        'password' => 'secret123',
        'enabled' => false,
    ]);

    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'driver@marilin.app',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login fails when user has no driver profile or driver is disabled', function (): void {
    $user = User::factory()->create([
        'email' => 'nodriver@marilin.app',
        'password' => 'secret123',
        'enabled' => true,
    ]);

    // No driver record
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nodriver@marilin.app',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    // Disabled driver record
    Driver::factory()->create([
        'user_id' => $user->id,
        'enabled' => false,
    ]);

    $response2 = $this->postJson('/api/v1/auth/login', [
        'email' => 'nodriver@marilin.app',
        'password' => 'secret123',
    ]);

    $response2->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
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
                'email' => $user->email,
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
            'message' => 'Logged out successfully',
        ]);

    expect($user->tokens()->count())->toBe(0);
});
