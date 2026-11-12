<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    use ResolvesCatalogNeighborhood;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'phone_number' => 'nullable|string|max:50',
            'repartidor_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')->where('enabled', true)],
            'street' => 'required|string|max:200',
            'apartment' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:20',
            'visual_reference' => 'nullable|string|max:500',
            'neighborhood' => ['nullable', 'string', Rule::exists('neighborhoods', 'name')->where('enabled', true)],
            // New delivery preferences only; the existing ones are changed from the customer page.
            ...CustomerPreferenceRules::rows(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repartidor_id.integer' => 'El repartidor debe estar habilitado.',
            'repartidor_id.exists' => 'El repartidor debe estar habilitado.',
        ];
    }
}
