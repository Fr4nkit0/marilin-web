<?php

declare(strict_types=1);

namespace App\Services\Map;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class MapCache
{
    public function store(): CacheRepository
    {
        $store = config('services.map.cache_store');
        if ($store && array_key_exists($store, config('cache.stores', []))) {
            return Cache::store($store);
        }

        return Cache::store();
    }
}
