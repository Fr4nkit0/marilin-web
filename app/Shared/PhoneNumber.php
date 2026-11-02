<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Every phone is a Salta number written one way, "387 587-4823", whether it comes from the web or the app. The
 * country prefix (54, or 549 for mobiles) and the trunk 0 are dropped; a number that is not ten digits once cleaned
 * cannot be read that way, so it is kept as it was typed.
 */
final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        $digits = match (true) {
            strlen($digits) === 13 && str_starts_with($digits, '549') => substr($digits, 3),
            strlen($digits) === 12 && str_starts_with($digits, '54') => substr($digits, 2),
            strlen($digits) === 11 && str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        if (strlen($digits) !== 10) {
            return $value;
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).'-'.substr($digits, 6);
    }
}
