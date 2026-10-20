<?php

declare(strict_types=1);

namespace App\Services\Map;

use App\Models\CustomerAddress;
use Illuminate\Support\Facades\Http;

class GeocodingService
{
    private const int CACHE_TTL_SECONDS = 2592000;

    public function __construct(private readonly MapCache $cache) {}

    /**
     * Searches local customer addresses first and falls back to Nominatim.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query): array
    {
        $local = $this->searchLocal($query);

        if ($local !== []) {
            return $local;
        }

        return $this->searchNominatim($query);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchLocal(string $query): array
    {
        return CustomerAddress::query()
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
            ->get()
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchNominatim(string $query): array
    {
        $cacheKey = 'map:geocode:'.md5(mb_strtolower($query));
        $cache = $this->cache->store();

        if ($cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }

        $nominatimUrl = rtrim((string) config('services.nominatim.url', 'https://nominatim.openstreetmap.org'), '/');

        $response = Http::withUserAgent(TileProxy::USER_AGENT)
            ->timeout(10)
            ->get("{$nominatimUrl}/search", [
                'q' => $query,
                'format' => 'json',
                'viewbox' => SaltaBoundingBox::viewbox(),
                'bounded' => 1,
                'limit' => 10,
            ]);

        if ($response->failed()) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rawItems */
        $rawItems = $response->json();

        $results = collect($rawItems)
            ->filter(fn (array $item): bool => SaltaBoundingBox::contains((float) ($item['lat'] ?? 0), (float) ($item['lon'] ?? 0)))
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

        $cache->put($cacheKey, $results, self::CACHE_TTL_SECONDS);

        return $results;
    }
}
