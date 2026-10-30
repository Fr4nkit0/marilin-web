<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

/**
 * The shape of a delivery preference typed on the web: a day (0 = Sunday, 6 = Saturday) and an optional window.
 * Whether the window ends after it starts, and whether the slot is free, is checked by CustomerIntake.
 */
final class CustomerPreferenceRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function for(string $prefix = ''): array
    {
        return [
            "{$prefix}day_of_week" => ['required', 'integer', 'between:0,6'],
            "{$prefix}time_window_start" => ['nullable', 'date_format:H:i'],
            "{$prefix}time_window_end" => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * The rows a customer form adds: `preferences.*.day_of_week`, ...
     *
     * @return array<string, list<string>>
     */
    public static function rows(): array
    {
        return [
            'preferences' => ['nullable', 'array'],
            ...self::for('preferences.*.'),
        ];
    }
}
