<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'marcos.morales@marilin.com',
        'password' => 'secret123',
        'enabled' => true,
    ]);
    Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
});

function apiLogin(string $email, string $password, string $ip = '10.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

test('the api login answers 429 with the seconds to wait after 5 failed attempts for the same email and ip', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin('marcos.morales@marilin.com', 'wrong')->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    $response = apiLogin('marcos.morales@marilin.com', 'wrong');

    $response->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonValidationErrors(['email']);

    $seconds = (int) $response->headers->get('Retry-After');

    expect($seconds)->toBeGreaterThan(0)->toBeLessThanOrEqual(60)
        ->and($response->json('retry_after'))->toBe($seconds)
        ->and($response->json('errors.email.0'))->toBe("Demasiados intentos de inicio de sesión. Intente de nuevo en {$seconds} segundos.");
});

test('the api login stays locked even with the right password once the limit is reached, and issues no token', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin('marcos.morales@marilin.com', 'wrong');
    }

    apiLogin('marcos.morales@marilin.com', 'secret123')->assertStatus(429)->assertJsonMissingPath('data.token');

    expect($this->user->tokens()->count())->toBe(0);
});

test('the api login lock counts the email case-insensitively', function (): void {
    foreach (range(1, 5) as $attempt) {
        apiLogin($attempt % 2 === 0 ? 'MARCOS.MORALES@marilin.com' : 'marcos.morales@marilin.com', 'wrong');
    }

    apiLogin('Marcos.Morales@marilin.com', 'wrong')->assertStatus(429);
});

test('the api login lock is per email and ip: another ip or another email is not locked', function (): void {
    $other = User::factory()->create(['email' => 'juan@marilin.com', 'password' => 'secret123', 'enabled' => true]);
    Driver::factory()->create(['user_id' => $other->id, 'enabled' => true]);

    foreach (range(1, 5) as $attempt) {
        apiLogin('marcos.morales@marilin.com', 'wrong');
    }

    apiLogin('marcos.morales@marilin.com', 'secret123', '10.0.0.2')->assertOk();
    apiLogin('juan@marilin.com', 'secret123')->assertOk();
});

test('a successful api login clears the failed attempts', function (): void {
    foreach (range(1, 4) as $attempt) {
        apiLogin('marcos.morales@marilin.com', 'wrong');
    }

    apiLogin('marcos.morales@marilin.com', 'secret123')->assertOk();

    foreach (range(1, 4) as $attempt) {
        apiLogin('marcos.morales@marilin.com', 'wrong')->assertStatus(422);
    }

    apiLogin('marcos.morales@marilin.com', 'secret123')->assertOk();
});

test('the api login finds the email case-insensitively without changing the stored email', function (): void {
    $response = apiLogin('Marcos.Morales@marilin.com', 'secret123');

    $response->assertOk()->assertJsonPath('data.user.email', 'marcos.morales@marilin.com');

    expect($this->user->fresh()->email)->toBe('marcos.morales@marilin.com');
});

test('the api login finds a stored email with capitals when the app sends it lowercased', function (): void {
    $this->user->forceFill(['email' => 'Esteban.Ruiz@marilin.com'])->save();

    apiLogin('esteban.ruiz@marilin.com', 'secret123')->assertOk()->assertJsonPath('data.user.email', 'Esteban.Ruiz@marilin.com');
});

test('a case-insensitive email with a wrong password still fails with the usual message', function (): void {
    apiLogin('Marcos.Morales@marilin.com', 'wrong')
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', __('auth.failed'));
});

test('forwarded headers and padded or recased emails do not dodge the api lock', function (): void {
    foreach ([' MARCOS.MORALES@MARILIN.COM ', 'Marcos.Morales@marilin.com', 'marcos.MORALES@marilin.com', 'marcos.morales@marilin.com', 'MARCOS.morales@marilin.com'] as $i => $email) {
        test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => "1.2.3.{$i}"])
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong'])->assertStatus(422);
    }

    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders(['X-Forwarded-For' => '9.9.9.9'])
        ->postJson('/api/v1/auth/login', ['email' => 'marcos.morales@marilin.com', 'password' => 'secret123'])
        ->assertStatus(429)->assertHeader('Retry-After');
});
