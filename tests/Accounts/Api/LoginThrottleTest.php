<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => null,
        'username' => 'mmorales',
        'password' => 'secret123',
        'enabled' => true,
    ]);
    Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
});

function apiLogin(string $username, string $password, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password]);
}

test('the api login answers 429 with the seconds to wait after 5 failed attempts for the same username and ip', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin('mmorales', 'wrong')->assertStatus(422)->assertJsonValidationErrors(['username']);
    }

    $response = apiLogin('mmorales', 'wrong');

    $response->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonValidationErrors(['username']);

    $seconds = (int) $response->headers->get('Retry-After');

    expect($seconds)->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and($response->json('retry_after'))->toBe($seconds)
        ->and($response->json('errors.username.0'))->toBe("Demasiados intentos de inicio de sesión. Intente de nuevo en {$seconds} segundos.");
});

test('the api login stays locked even with the right password once the limit is reached, and issues no token', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin('mmorales', 'wrong');
    }

    apiLogin('mmorales', 'secret123')->assertStatus(429)->assertJsonMissingPath('data.token');

    expect($this->user->tokens()->count())->toBe(0);
});

test('the api login lock counts the username case-insensitively', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin($attempt % 2 === 0 ? 'MMORALES' : 'mmorales', 'wrong');
    }

    apiLogin('MMorales', 'wrong')->assertStatus(429);
});

test('the api login lock is per username and ip: another ip or another username is not locked', function (): void {
    $other = User::factory()->create(['email' => null, 'username' => 'jperez', 'password' => 'secret123', 'enabled' => true]);
    Driver::factory()->create(['user_id' => $other->id, 'enabled' => true]);

    foreach (range(1, 5) as $attempt) {
        apiLogin('mmorales', 'wrong');
    }

    apiLogin('mmorales', 'secret123', '10.0.0.2')->assertOk();
    apiLogin('jperez', 'secret123')->assertOk();
});

test('a successful api login clears the failed attempts', function (): void {
    foreach (range(1, 4) as $attempt) {
        apiLogin('mmorales', 'wrong');
    }

    apiLogin('mmorales', 'secret123')->assertOk();

    foreach (range(1, 4) as $attempt) {
        apiLogin('mmorales', 'wrong')->assertStatus(422);
    }

    apiLogin('mmorales', 'secret123')->assertOk();
});

test('the api login finds a stored username with capitals when the app sends it lowercased', function (): void {
    $this->user->forceFill(['username' => 'ERuiz'])->save();

    apiLogin('eruiz', 'secret123')->assertOk()->assertJsonPath('data.user.username', 'ERuiz');

    expect($this->user->fresh()->username)->toBe('ERuiz');
});

test('a case-insensitive username with a wrong password still fails with the usual message', function (): void {
    apiLogin('MMorales', 'wrong')
        ->assertStatus(422)
        ->assertJsonPath('errors.username.0', __('auth.failed'));
});

test('forwarded headers and padded or recased usernames do not dodge the api lock', function (): void {
    foreach ([' MMORALES ', 'MMorales', 'mMORALES', 'mmorales', 'MMORales'] as $i => $username) {
        test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => "1.2.3.{$i}"])
            ->postJson('/api/v1/auth/login', ['username' => $username, 'password' => 'wrong'])->assertStatus(422);
    }

    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => '9.9.9.9'])
        ->postJson('/api/v1/auth/login', ['username' => 'mmorales', 'password' => 'secret123'])
        ->assertStatus(429)->assertHeader('Retry-After');
});
