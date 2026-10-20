<?php

declare(strict_types=1);

namespace App\Services\Map;

use Illuminate\Support\Facades\Http;

class RoutingService
{
    private const int CACHE_TTL_SECONDS = 604800;

    public function __construct(private readonly MapCache $cache) {}

    /**
     * Returns the OSRM route payload, or null when the routing service fails.
     *
     * @return array<string, mixed>|null
     */
    public function route(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $cacheKey = sprintf('map:route:%s:%s:%s:%s', $originLat, $originLng, $destLat, $destLng);
        $cache = $this->cache->store();

        if ($cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }

        $osrmUrl = rtrim((string) config('services.osrm.url', 'https://router.project-osrm.org'), '/');
        $endpoint = "{$osrmUrl}/route/v1/driving/{$originLng},{$originLat};{$destLng},{$destLat}?overview=full&geometries=geojson";

        $response = Http::timeout(10)->get($endpoint);

        if ($response->failed()) {
            return null;
        }

        $routeData = $response->json();
        $cache->put($cacheKey, $routeData, self::CACHE_TTL_SECONDS);

        return $routeData;
    }
}
