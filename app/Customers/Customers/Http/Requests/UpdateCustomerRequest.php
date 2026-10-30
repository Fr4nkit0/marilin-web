<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
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
            'repartidor_id' => 'nullable|exists:drivers,id',
            'street' => 'required|string|max:200',
            'apartment' => 'nullable|string|max:20',
            'floor' => 'nullable|string|max:20',
            'visual_reference' => 'nullable|string|max:500',
            'neighborhood' => ['nullable', 'string', Rule::exists('neighborhoods', 'name')->where('enabled', true)],
        ];
    }
}
