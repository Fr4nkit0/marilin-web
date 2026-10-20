<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('map endpoints reject requests without a token', function (string $uri): void {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    'tile' => '/api/v1/map/tiles/12/1383/2470.png',
    'route' => '/api/v1/map/route',
    'geocode' => '/api/v1/map/geocode',
]);
