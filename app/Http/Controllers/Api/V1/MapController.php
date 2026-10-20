<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GeocodeRequest;
use App\Http\Requests\Api\V1\RouteRequest;
use App\Http\Requests\Api\V1\TileRequest;
use App\Services\Map\GeocodingService;
use App\Services\Map\RoutingService;
use App\Services\Map\TileProxy;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MapController extends Controller
{
    public function tile(TileRequest $request, TileProxy $tiles): BinaryFileResponse|JsonResponse
    {
        $filePath = $tiles->path($request->zoom(), $request->tileX(), $request->tileY());

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
        $routeData = $routing->route(...$request->origin(), ...$request->destination());

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
