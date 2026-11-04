<?php

declare(strict_types=1);

namespace App\Customers\Neighborhoods\Http\Requests;

use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Shared\TextMatch;
use Closure;

/**
 * The name rules shared by the forms that create and rename a neighborhood.
 */
final class NeighborhoodNameRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?Neighborhood $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', self::uniqueName($ignore)],
        ];
    }

    /**
     * The app matches a typed neighborhood without minding case or accents, so two may not differ only by them.
     */
    public static function uniqueName(?Neighborhood $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = TextMatch::equals(Neighborhood::query(), 'name', (string) $value)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail('Ya hay un barrio con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre del barrio.',
            'name.string' => 'Ingresá el nombre del barrio.',
            'name.max' => 'El nombre puede tener hasta 255 caracteres.',
        ];
    }
}
