<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Dates sent by the app carry their own offset (usually UTC). The database stores wall-clock
 * time without a zone, so every client date is moved to the app timezone before it is saved
 * or compared; otherwise it would be read hours off.
 */
final class ClientTime
{
    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
