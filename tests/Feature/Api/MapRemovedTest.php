<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('the map endpoints no longer exist, even for a signed-in driver', function (string $uri): void {
    Sanctum::actingAs(User::factory()->create(['enabled' => true]), ['*']);

    $this->getJson($uri)->assertNotFound();
})->with([
    'tile' => '/api/v1/map/tiles/12/1383/2470.png',
    'route' => '/api/v1/map/route',
    'geocode' => '/api/v1/map/geocode',
]);
