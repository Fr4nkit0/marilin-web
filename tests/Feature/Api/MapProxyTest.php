<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['enabled' => true]);
    $this->driver = Driver::factory()->create(['user_id' => $this->user->id, 'enabled' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

test('tile endpoint rejects coordinates outside Salta bounding box with 422', function (): void {
    // Buenos Aires tile coordinates at zoom 12
    $response = $this->getJson('/api/v1/map/tiles/12/1383/2470.png');

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Requested map tile is outside Salta bounding box.',
        ]);
});

test('tile endpoint downloads, caches to disk, and serves with 30-day Cache-Control header', function (): void {
    $z = 12;
    $x = 1303;
    $y = 2339;
    $fakePngData = 'fake-png-content-bytes';
    $tilePath = storage_path("app/map/tiles/{$z}/{$x}/{$y}.png");

    if (File::exists($tilePath)) {
        File::delete($tilePath);
    }

    Http::fake([
        "https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png" => Http::response($fakePngData, 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    $response = $this->get("/api/v1/map/tiles/{$z}/{$x}/{$y}.png");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))
        ->toContain('max-age=2592000')
        ->toContain('public');

    expect(File::exists($tilePath))->toBeTrue();
    expect(File::get($tilePath))->toBe($fakePngData);

    // Second request: served from disk without calling HTTP again
    Http::fake([
        "https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png" => Http::response('new-bytes', 500),
    ]);

    $secondResponse = $this->get("/api/v1/map/tiles/{$z}/{$x}/{$y}.png");
    $secondResponse->assertOk();

    // Clean up test file
    if (File::exists($tilePath)) {
        File::delete($tilePath);
    }
});

test('route endpoint rejects coordinates outside Salta bounding box with 422', function (): void {
    // Origin in Buenos Aires
    $response = $this->getJson('/api/v1/map/route?origin=-34.6037,-58.3816&destination=-24.7859,-65.4117');

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Coordinates are outside Salta bounding box.',
        ]);
});

test('route endpoint queries OSRM, caches in redis/cache, and returns route', function (): void {
    Cache::flush();

    $origin = '-24.7859,-65.4117';
    $destination = '-24.7900,-65.4050';

    $osrmMockData = [
        'code' => 'Ok',
        'routes' => [
            [
                'geometry' => 'test-polyline',
                'distance' => 1500.5,
                'duration' => 320.0,
            ],
        ],
    ];

    Http::fake([
        '*/route/v1/driving/*' => Http::response($osrmMockData, 200),
    ]);

    $response = $this->getJson("/api/v1/map/route?origin={$origin}&destination={$destination}");

    $response->assertOk()
        ->assertJson([
            'data' => $osrmMockData,
            'message' => null,
        ]);

    Http::assertSentCount(1);

    // Second request serves from cache without hitting OSRM
    $secondResponse = $this->getJson("/api/v1/map/route?origin={$origin}&destination={$destination}");

    $secondResponse->assertOk()
        ->assertJson([
            'data' => $osrmMockData,
            'message' => null,
        ]);

    Http::assertSentCount(1);
});

test('geocode endpoint prioritizes local database customer addresses', function (): void {
    $customer = Customer::factory()->create([
        'driver_id' => $this->driver->id,
        'name' => 'Empresa San Martin SA',
        'created_by' => $this->user->id,
        'enabled' => true,
    ]);

    $address = CustomerAddress::create([
        'customer_id' => $customer->id,
        'street' => 'Av San Martin 450',
        'latitude' => -24.7859,
        'longitude' => -65.4117,
        'is_primary' => true,
        'created_by' => $this->user->id,
        'enabled' => true,
    ]);

    Http::fake();

    $response = $this->getJson('/api/v1/map/geocode?q=San+Martin');

    $response->assertOk()
        ->assertJson([
            'data' => [
                [
                    'street' => 'Av San Martin 450',
                    'latitude' => -24.7859,
                    'longitude' => -65.4117,
                    'source' => 'local',
                    'customer_id' => $customer->id,
                    'address_id' => $address->id,
                ],
            ],
            'message' => null,
        ]);

    // External geocoder was NOT called
    Http::assertNothingSent();
});

test('geocode endpoint falls back to nominatim when no local address matches and caches results', function (): void {
    Cache::flush();

    $nominatimResponse = [
        [
            'place_id' => 12345,
            'display_name' => 'Plaza 9 de Julio, Salta, Capital, Salta, Argentina',
            'lat' => '-24.789123',
            'lon' => '-65.410456',
        ],
    ];

    Http::fake([
        '*/search*' => Http::response($nominatimResponse, 200),
    ]);

    $response = $this->getJson('/api/v1/map/geocode?q=Plaza+9+de+Julio');

    $response->assertOk()
        ->assertJson([
            'data' => [
                [
                    'street' => 'Plaza 9 de Julio, Salta, Capital, Salta, Argentina',
                    'latitude' => -24.789123,
                    'longitude' => -65.410456,
                    'source' => 'nominatim',
                ],
            ],
            'message' => null,
        ]);

    Http::assertSentCount(1);

    // Second request is served from cache
    $secondResponse = $this->getJson('/api/v1/map/geocode?q=Plaza+9+de+Julio');
    $secondResponse->assertOk()
        ->assertJson([
            'data' => [
                [
                    'source' => 'nominatim',
                ],
            ],
        ]);

    Http::assertSentCount(1);
});

test('tile endpoint rejects an invalid zoom level with 422', function (): void {
    $this->getJson('/api/v1/map/tiles/25/1/1.png')
        ->assertStatus(422)
        ->assertJson(['message' => 'Zoom level must be between 0 and 19.']);
});

test('route endpoint requires origin and destination', function (): void {
    $this->getJson('/api/v1/map/route')
        ->assertStatus(422)
        ->assertJson(['message' => 'Origin and destination coordinates are required.']);
});
