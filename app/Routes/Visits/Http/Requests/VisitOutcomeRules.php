<?php

declare(strict_types=1);

namespace App\Routes\Visits\Http\Requests;

use App\Routes\Visits\Models\VisitOutcome;
use App\Shared\TextMatch;
use Closure;

/**
 * The rules shared by the forms that create and edit a visit outcome. The code is not among them: it is never
 * taken from the form.
 */
final class VisitOutcomeRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?VisitOutcome $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', self::uniqueName($ignore)],
            'triggers_reschedule' => ['required', 'boolean'],
        ];
    }

    /**
     * The driver picks an outcome by its name, so two may not differ only by case or accents.
     */
    public static function uniqueName(?VisitOutcome $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = TextMatch::equals(VisitOutcome::query(), 'name', (string) $value)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail('Ya hay un resultado de visita con ese nombre.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre del resultado.',
            'name.string' => 'Ingresá el nombre del resultado.',
            'name.max' => 'El nombre puede tener hasta 100 caracteres.',
            'triggers_reschedule.required' => 'Indicá si el resultado reprograma la visita.',
            'triggers_reschedule.boolean' => 'Indicá si el resultado reprograma la visita.',
        ];
    }
}
