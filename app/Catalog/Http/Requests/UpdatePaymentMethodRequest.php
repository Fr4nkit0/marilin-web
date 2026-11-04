<?php

declare(strict_types=1);

namespace App\Catalog\Http\Requests;

use App\Catalog\Models\PaymentMethod;
use App\Shared\TextMatch;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renames a payment method; its code is never taken from the form.
 */
class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A method may keep its own name, for instance to fix its letter case.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var PaymentMethod $method */
        $method = $this->route('paymentMethod');

        return [
            'name' => ['required', 'string', 'max:80', $this->uniqueName($method)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingresá el nombre del medio de pago.',
            'name.string' => 'Ingresá el nombre del medio de pago.',
            'name.max' => 'El nombre puede tener hasta 80 caracteres.',
        ];
    }

    /**
     * Two methods may not differ only by case or accents: nobody could tell them apart.
     */
    private function uniqueName(PaymentMethod $method): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($method): void {
            $taken = TextMatch::equals(PaymentMethod::query(), 'name', (string) $value)
                ->whereKeyNot($method->getKey())
                ->exists();

            if ($taken) {
                $fail('Ya hay un medio de pago con ese nombre.');
            }
        };
    }
}
