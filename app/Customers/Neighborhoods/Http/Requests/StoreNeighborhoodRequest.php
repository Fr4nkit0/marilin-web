<?php

declare(strict_types=1);

namespace App\Customers\Neighborhoods\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNeighborhoodRequest extends FormRequest
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
        return NeighborhoodNameRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return NeighborhoodNameRules::messages();
    }
}
