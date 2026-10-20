<?php

declare(strict_types=1);

namespace App\Services\Map;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class TileProxy
{
    public const string USER_AGENT = 'MarilinApp/1.0 (contact@marilin.app)';

    /**
     * Returns the path of the cached tile, downloading it first when missing.
     * Returns null when the upstream tile server fails.
     */
    public function path(int $zoom, int $x, int $y): ?string
    {
        $filePath = storage_path("app/map/tiles/{$zoom}/{$x}/{$y}.png");

        if (File::exists($filePath)) {
            return $filePath;
        }

        File::ensureDirectoryExists(dirname($filePath));

        $upstream = Http::withUserAgent(self::USER_AGENT)
            ->timeout(10)
            ->get("https://tile.openstreetmap.org/{$zoom}/{$x}/{$y}.png");

        if ($upstream->failed()) {
            return null;
        }

        File::put($filePath, $upstream->body());

        return $filePath;
    }
}
