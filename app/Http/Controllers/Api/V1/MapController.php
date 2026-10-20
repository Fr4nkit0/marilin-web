<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GeocodeRequest;
use App\Http\Requests\Api\V1\RouteRequest;
use App\Services\Map\GeocodingService;
use App\Services\Map\RoutingService;
use App\Services\Map\SaltaBoundingBox;
use App\Services\Map\TileProxy;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MapController extends Controller
{
    public function tile(TileProxy $tiles, int|string $z, int|string $x, int|string $y): BinaryFileResponse|JsonResponse
    {
        $zoom = (int) $z;
        $tileX = (int) $x;
        $tileY = (int) $y;

        if ($zoom < 0 || $zoom > 19) {
            return response()->json([
                'message' => 'Zoom level must be between 0 and 19.',
                'errors' => ['z' => ['Invalid zoom level']],
            ], 422);
        }

        $maxCoord = 2 ** $zoom;
        if ($tileX < 0 || $tileX >= $maxCoord || $tileY < 0 || $tileY >= $maxCoord) {
            return response()->json([
                'message' => 'Tile coordinates out of range for zoom level.',
                'errors' => ['coordinates' => ['Invalid tile coordinate']],
            ], 422);
        }

        if (! SaltaBoundingBox::tileOverlaps($zoom, $tileX, $tileY)) {
            return response()->json([
                'message' => 'Requested map tile is outside Salta bounding box.',
                'errors' => ['tile' => ['Tile does not overlap with Salta bounding box']],
            ], 422);
        }

        $filePath = $tiles->path($zoom, $tileX, $tileY);

        if ($filePath === null) {
            return response()->json(['message' => 'Failed to fetch upstream tile.'], 502);
        }

        return response()->file($filePath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }

    public function route(RouteRequest $request, RoutingService $routing): JsonResponse
    {
        $origin = $request->origin();
        $destination = $request->destination();

        if ($origin === null || $destination === null) {
            return response()->json([
                'message' => 'Origin and destination coordinates are required.',
                'errors' => [
                    'coordinates' => ['Provide origin and destination as "lat,lng" or separate lat/lng fields.'],
                ],
            ], 422);
        }

        if (! SaltaBoundingBox::contains(...$origin) || ! SaltaBoundingBox::contains(...$destination)) {
            return response()->json([
                'message' => 'Coordinates are outside Salta bounding box.',
                'errors' => [
                    'coordinates' => ['Coordinates must be within Salta bounds [-26.5, -22.0], [-68.5, -62.0].'],
                ],
            ], 422);
        }

        $routeData = $routing->route(...$origin, ...$destination);

        if ($routeData === null) {
            return response()->json(['message' => 'OSRM routing service unavailable.'], 502);
        }

        return response()->json(['data' => $routeData, 'message' => null]);
    }

    public function geocode(GeocodeRequest $request, GeocodingService $geocoding): JsonResponse
    {
        return response()->json([
            'data' => $geocoding->search($request->searchTerm()),
            'message' => null,
        ]);
    }
}
