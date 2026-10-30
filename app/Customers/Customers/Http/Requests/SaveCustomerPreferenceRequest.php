<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCustomerPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return CustomerPreferenceRules::for();
    }
}
