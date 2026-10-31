<?php

declare(strict_types=1);

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-20 18:00:00');
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true, 'updated_at' => '2026-10-01 10:00:00']);
    Sanctum::actingAs($this->user, ['*']);
});

test('a successful sync stamps the driver last sync without touching the driver itself', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertOk();

    $driver = $this->driver->fresh();
    expect($driver->last_synced_at?->toIso8601String())->toBe('2026-10-20T18:00:00-03:00')
        ->and($driver->updated_at->toDateTimeString())->toBe('2026-10-01 10:00:00');
})->with([
    'bootstrap' => ['GET', '/api/v1/sync/bootstrap'],
    'pull' => ['GET', '/api/v1/sync/pull'],
    'push' => ['POST', '/api/v1/sync/push'],
]);

test('a failed sync, the profile and a token refresh do not count as a sync', function (): void {
    $this->getJson('/api/v1/sync/pull?page=0')->assertUnprocessable();
    $this->getJson('/api/v1/auth/me')->assertOk();
    $this->postJson('/api/v1/auth/refresh');

    expect($this->driver->fresh()->last_synced_at)->toBeNull();
});
