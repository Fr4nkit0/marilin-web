<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Services\Map\SaltaBoundingBox;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MapController extends Controller
{
    private function cacheStore(): CacheRepository
    {
        $store = config('services.map.cache_store');
        if ($store && array_key_exists($store, config('cache.stores', []))) {
            return Cache::store($store);
        }

        return Cache::store();
    }

    public function tile(int|string $z, int|string $x, int|string $y): BinaryFileResponse|JsonResponse
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

        $filePath = storage_path("app/map/tiles/{$zoom}/{$tileX}/{$tileY}.png");

        if (! File::exists($filePath)) {
            File::ensureDirectoryExists(dirname($filePath));

            $upstream = Http::withUserAgent('MarilinApp/1.0 (contact@marilin.app)')
                ->timeout(10)
                ->get("https://tile.openstreetmap.org/{$zoom}/{$tileX}/{$tileY}.png");

            if ($upstream->failed()) {
                return response()->json([
                    'message' => 'Failed to fetch upstream tile.',
                ], 502);
            }

            File::put($filePath, $upstream->body());
        }

        return response()->file($filePath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }

    public function route(Request $request): JsonResponse
    {
        $originLat = null;
        $originLng = null;
        $destLat = null;
        $destLng = null;

        if ($request->filled('origin') && is_string($request->input('origin'))) {
            $parts = explode(',', $request->string('origin')->toString());
            if (count($parts) === 2) {
                $originLat = (float) trim($parts[0]);
                $originLng = (float) trim($parts[1]);
            }
        } elseif ($request->filled('origin_lat') && $request->filled('origin_lng')) {
            $originLat = (float) $request->input('origin_lat');
            $originLng = (float) $request->input('origin_lng');
        }

        if ($request->filled('destination') && is_string($request->input('destination'))) {
            $parts = explode(',', $request->string('destination')->toString());
            if (count($parts) === 2) {
                $destLat = (float) trim($parts[0]);
                $destLng = (float) trim($parts[1]);
            }
        } elseif ($request->filled('destination_lat') && $request->filled('destination_lng')) {
            $destLat = (float) $request->input('destination_lat');
            $destLng = (float) $request->input('destination_lng');
        }

        if ($originLat === null || $originLng === null || $destLat === null || $destLng === null) {
            return response()->json([
                'message' => 'Origin and destination coordinates are required.',
                'errors' => [
                    'coordinates' => ['Provide origin and destination as "lat,lng" or separate lat/lng fields.'],
                ],
            ], 422);
        }

        if (! SaltaBoundingBox::contains($originLat, $originLng) || ! SaltaBoundingBox::contains($destLat, $destLng)) {
            return response()->json([
                'message' => 'Coordinates are outside Salta bounding box.',
                'errors' => [
                    'coordinates' => ['Coordinates must be within Salta bounds [-26.5, -22.0], [-68.5, -62.0].'],
                ],
            ], 422);
        }

        $cacheKey = sprintf('map:route:%s:%s:%s:%s', $originLat, $originLng, $destLat, $destLng);
        $cache = $this->cacheStore();

        if ($cache->has($cacheKey)) {
            return response()->json([
                'data' => $cache->get($cacheKey),
                'message' => null,
            ]);
        }

        $osrmUrl = rtrim((string) config('services.osrm.url', 'https://router.project-osrm.org'), '/');
        $endpoint = "{$osrmUrl}/route/v1/driving/{$originLng},{$originLat};{$destLng},{$destLat}?overview=full&geometries=geojson";

        $response = Http::timeout(10)->get($endpoint);

        if ($response->failed()) {
            return response()->json([
                'message' => 'OSRM routing service unavailable.',
            ], 502);
        }

        $routeData = $response->json();
        // Cache for 7 days (604800 seconds)
        $cache->put($cacheKey, $routeData, 604800);

        return response()->json([
            'data' => $routeData,
            'message' => null,
        ]);
    }

    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $query = trim($request->string('q')->toString());

        // 1. Search local customer addresses first
        $localAddresses = CustomerAddress::query()
            ->with('customer')
            ->where('enabled', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($builder) use ($query): void {
                $builder->where('street', 'ilike', "%{$query}%")
                    ->orWhere('visual_reference', 'ilike', "%{$query}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($query): void {
                        $customerQuery->where('name', 'ilike', "%{$query}%");
                    });
            })
            ->limit(10)
            ->get();

        $localResults = $localAddresses
            ->filter(fn (CustomerAddress $addr): bool => SaltaBoundingBox::contains((float) $addr->latitude, (float) $addr->longitude))
            ->map(fn (CustomerAddress $addr): array => [
                'name' => $addr->customer?->name ? "{$addr->customer->name} - {$addr->street}" : (string) $addr->street,
                'street' => (string) $addr->street,
                'latitude' => (float) $addr->latitude,
                'longitude' => (float) $addr->longitude,
                'customer_id' => $addr->customer_id,
                'address_id' => $addr->id,
                'source' => 'local',
            ])
            ->values()
            ->all();

        if (count($localResults) > 0) {
            return response()->json([
                'data' => $localResults,
                'message' => null,
            ]);
        }

        // 2. Fallback to Nominatim with Salta viewbox constraint
        $cacheKey = 'map:geocode:'.md5(mb_strtolower($query));
        $cache = $this->cacheStore();

        if ($cache->has($cacheKey)) {
            return response()->json([
                'data' => $cache->get($cacheKey),
                'message' => null,
            ]);
        }

        $nominatimUrl = rtrim((string) config('services.nominatim.url', 'https://nominatim.openstreetmap.org'), '/');

        $response = Http::withUserAgent('MarilinApp/1.0 (contact@marilin.app)')
            ->timeout(10)
            ->get("{$nominatimUrl}/search", [
                'q' => $query,
                'format' => 'json',
                'viewbox' => '-68.5,-22.0,-62.0,-26.5',
                'bounded' => 1,
                'limit' => 10,
            ]);

        if ($response->failed()) {
            return response()->json([
                'data' => [],
                'message' => null,
            ]);
        }

        /** @var array<int, array<string, mixed>> $rawItems */
        $rawItems = $response->json();

        $remoteResults = collect($rawItems)
            ->filter(function (array $item): bool {
                $lat = (float) ($item['lat'] ?? 0);
                $lon = (float) ($item['lon'] ?? 0);

                return SaltaBoundingBox::contains($lat, $lon);
            })
            ->map(fn (array $item): array => [
                'name' => (string) ($item['display_name'] ?? ($item['name'] ?? '')),
                'street' => (string) ($item['display_name'] ?? ''),
                'latitude' => (float) $item['lat'],
                'longitude' => (float) $item['lon'],
                'customer_id' => null,
                'address_id' => null,
                'source' => 'nominatim',
            ])
            ->values()
            ->all();

        // Cache for 30 days (2592000 seconds)
        $cache->put($cacheKey, $remoteResults, 2592000);

        return response()->json([
            'data' => $remoteResults,
            'message' => null,
        ]);
    }
}
