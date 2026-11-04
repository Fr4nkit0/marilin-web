<?php

declare(strict_types=1);

namespace App\Catalog\Http\Requests;

use App\Catalog\Models\ContainerType;
use App\Shared\TextMatch;
use Closure;

/**
 * The rules shared by the forms that create and edit a container type. The code is not among them: it is
 * generated from the description and never taken from the form.
 */
final class ContainerTypeRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?ContainerType $ignore = null): array
    {
        return [
            'description' => ['required', 'string', 'max:160', self::uniqueDescription($ignore)],
        ];
    }

    /**
     * Two types may not differ only by case or accents: nobody could tell them apart.
     */
    public static function uniqueDescription(?ContainerType $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = TextMatch::equals(ContainerType::query(), 'description', (string) $value)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail('Ya hay un tipo de envase con esa descripción.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'description.required' => 'Ingresá la descripción del envase.',
            'description.string' => 'Ingresá la descripción del envase.',
            'description.max' => 'La descripción puede tener hasta 160 caracteres.',
        ];
    }
}
