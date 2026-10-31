<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-21 10:00:00');
    $this->user = User::factory()->create(['email' => 'driver@marilin.app', 'password' => 'secret123', 'enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
});

/**
 * Logs the driver in and returns the token data of the response.
 *
 * @return array{token: string, refresh_token: string, expires_at: string, refresh_expires_at: string}
 */
function loginTokens(): array
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => 'driver@marilin.app',
        'password' => 'secret123',
        'device_name' => 'android-phone',
    ])->assertOk()->json('data');
}

function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

test('login returns an access token for a day and a refresh token for thirty days', function (): void {
    $tokens = loginTokens();

    $access = PersonalAccessToken::findToken($tokens['token']);
    $refresh = PersonalAccessToken::findToken($tokens['refresh_token']);

    expect($access->abilities)->toBe(['api'])
        ->and($access->expires_at->toDateTimeString())->toBe('2026-10-22 10:00:00')
        ->and($refresh->abilities)->toBe(['refresh'])
        ->and($refresh->expires_at->toDateTimeString())->toBe('2026-11-20 10:00:00')
        ->and($tokens['expires_at'])->toBe($access->expires_at->toIso8601String())
        ->and($tokens['refresh_expires_at'])->toBe($refresh->expires_at->toIso8601String());
});

test('the token lifetimes come from the configuration', function (): void {
    config(['sanctum.access_token_ttl' => 60, 'sanctum.refresh_token_ttl' => 120]);

    $tokens = loginTokens();

    expect(PersonalAccessToken::findToken($tokens['token'])->expires_at->toDateTimeString())->toBe('2026-10-21 11:00:00')
        ->and(PersonalAccessToken::findToken($tokens['refresh_token'])->expires_at->toDateTimeString())->toBe('2026-10-21 12:00:00');
});

test('a refresh token gets a new pair and revokes the old one', function (): void {
    $old = loginTokens();

    $response = $this->postJson('/api/v1/auth/refresh', [], bearer($old['refresh_token']));

    $response->assertOk()->assertJsonStructure([
        'data' => ['token', 'refresh_token', 'expires_at', 'refresh_expires_at', 'user' => ['id', 'email']],
        'message',
    ])->assertJsonPath('message', 'Sesión renovada.');
    $new = $response->json('data');

    expect(PersonalAccessToken::findToken($old['token']))->toBeNull()
        ->and(PersonalAccessToken::findToken($old['refresh_token']))->toBeNull()
        ->and($this->user->tokens()->count())->toBe(2);

    app('auth')->forgetGuards();
    $this->getJson('/api/v1/auth/me', bearer($new['token']))->assertOk();
});

test('a used refresh token cannot be used again', function (): void {
    $old = loginTokens();
    $this->postJson('/api/v1/auth/refresh', [], bearer($old['refresh_token']))->assertOk();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/refresh', [], bearer($old['refresh_token']))->assertUnauthorized();
});

test('a refresh token cannot call the api', function (): void {
    $tokens = loginTokens();

    $this->getJson('/api/v1/auth/me', bearer($tokens['refresh_token']))->assertForbidden();
    $this->getJson('/api/v1/sync/bootstrap', bearer($tokens['refresh_token']))->assertForbidden();
});

test('an access token cannot refresh', function (): void {
    $tokens = loginTokens();

    $this->postJson('/api/v1/auth/refresh', [], bearer($tokens['token']))->assertForbidden();
});

test('an expired access token is rejected with 401', function (): void {
    $tokens = loginTokens();
    $this->travelTo('2026-10-22 10:00:01');

    $this->getJson('/api/v1/auth/me', bearer($tokens['token']))->assertUnauthorized();
});

test('an expired refresh token is rejected with 401', function (): void {
    $tokens = loginTokens();
    $this->travelTo('2026-11-20 10:00:01');

    $this->postJson('/api/v1/auth/refresh', [], bearer($tokens['refresh_token']))->assertUnauthorized();
});

test('a driver disabled after login cannot refresh and loses the session', function (): void {
    $tokens = loginTokens();
    $this->driver->update(['enabled' => false]);

    $this->postJson('/api/v1/auth/refresh', [], bearer($tokens['refresh_token']))->assertUnauthorized();

    expect($this->user->tokens()->count())->toBe(0);
});

test('logout revokes the access token and its refresh token', function (): void {
    $tokens = loginTokens();

    $this->postJson('/api/v1/auth/logout', [], bearer($tokens['token']))->assertOk();
    app('auth')->forgetGuards();

    expect($this->user->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/auth/refresh', [], bearer($tokens['refresh_token']))->assertUnauthorized();
});

test('refreshing or logging out on one phone keeps the session of another phone with the same name', function (): void {
    $phoneA = loginTokens();
    $phoneB = loginTokens();

    $refreshedA = $this->postJson('/api/v1/auth/refresh', [], bearer($phoneA['refresh_token']))->assertOk()->json('data');
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/auth/me', bearer($phoneB['token']))->assertOk();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/logout', [], bearer($refreshedA['token']))->assertOk();
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/refresh', [], bearer($phoneB['refresh_token']))->assertOk();
    expect($this->user->tokens()->count())->toBe(2);
});

test('a token issued before expiring tokens existed keeps working', function (): void {
    $legacy = $this->user->createToken('old-phone')->plainTextToken;

    $this->getJson('/api/v1/auth/me', bearer($legacy))->assertOk();
});
