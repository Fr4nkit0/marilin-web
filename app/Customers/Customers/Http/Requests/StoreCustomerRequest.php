<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
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
            'initial_balance' => 'nullable|numeric|min:0',
            'bidon_20l_balance' => 'nullable|integer|min:0',
            'sifon_2l_balance' => 'nullable|integer|min:0',
        ];
    }
}
