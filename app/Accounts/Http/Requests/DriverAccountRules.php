<?php

declare(strict_types=1);

namespace App\Accounts\Http\Requests;

use App\Accounts\Models\User;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Rules and messages shared by the forms that create, edit and reset a driver account.
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
            'email' => ['required', 'string', 'email', 'max:255', self::uniqueEmail($ignore)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($ignore)],
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
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre.',
            'email.required' => 'Ingresá el email.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'Ya hay una cuenta con ese email.',
            'username.unique' => 'Ya hay una cuenta con ese usuario.',
            'phone_number.max' => 'El teléfono puede tener hasta 20 caracteres.',
            'password.required' => 'Ingresá la contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
        ];
    }
}
