<?php

declare(strict_types=1);

namespace App\Street\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHomePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('enabled', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'El monto tiene que ser mayor a cero.',
            'payment_method_id.exists' => 'El método de pago no existe o está deshabilitado.',
        ];
    }
}
