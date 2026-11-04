<?php

declare(strict_types=1);

namespace App\Accounts\Http\Requests;

use App\Accounts\Models\User;
use Closure;

/**
 * Rules and messages shared by the forms that create, edit and reset a driver account.
 * A driver signs in to the app with the username and has no email.
 */
final class DriverAccountRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function profile(?User $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', self::uniqueUsername($ignore)],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Login matches the email whatever its letter case, so two accounts may not differ only by it.
     */
    public static function uniqueEmail(?User $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = User::query()
                ->emailIs((string) $value)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail('Ya hay una cuenta con ese email.');
            }
        };
    }

    /**
     * The app login matches the username whatever its letter case, so two accounts may not differ only by it.
     */
    public static function uniqueUsername(?User $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = User::query()
                ->usernameIs((string) $value)
                ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail('Ya hay una cuenta con ese usuario.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre.',
            'username.required' => 'Ingresá el usuario.',
            'username.max' => 'El usuario puede tener hasta 255 caracteres.',
            'phone_number.max' => 'El teléfono puede tener hasta 20 caracteres.',
            'password.required' => 'Ingresá la contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
        ];
    }
}
