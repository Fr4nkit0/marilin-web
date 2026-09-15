<?php

declare(strict_types=1);

namespace App\Services\Map;

class SaltaBoundingBox
{
    public const float MIN_LAT = -26.5;

    public const float MAX_LAT = -22.0;

    public const float MIN_LNG = -68.5;

    public const float MAX_LNG = -62.0;

    public static function contains(float $latitude, float $longitude): bool
    {
        return $latitude >= self::MIN_LAT
            && $latitude <= self::MAX_LAT
            && $longitude >= self::MIN_LNG
            && $longitude <= self::MAX_LNG;
    }

    public static function tileOverlaps(int $z, int $x, int $y): bool
    {
        if ($z < 6) {
            return true;
        }

        $n = 2 ** $z;
        $tileLngMin = $x / $n * 360.0 - 180.0;
        $tileLngMax = ($x + 1) / $n * 360.0 - 180.0;

        $latMinRad = atan(sinh(pi() * (1 - 2 * ($y + 1) / $n)));
        $latMaxRad = atan(sinh(pi() * (1 - 2 * $y / $n)));

        $tileLatMin = rad2deg($latMinRad);
        $tileLatMax = rad2deg($latMaxRad);

        $latOverlap = ! ($tileLatMax < self::MIN_LAT || $tileLatMin > self::MAX_LAT);
        $lngOverlap = ! ($tileLngMax < self::MIN_LNG || $tileLngMin > self::MAX_LNG);

        return $latOverlap && $lngOverlap;
    }
}
