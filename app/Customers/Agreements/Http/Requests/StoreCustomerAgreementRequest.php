<?php

declare(strict_types=1);

namespace App\Customers\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerAgreementRequest extends FormRequest
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('enabled', true)],
            'unit_price' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
