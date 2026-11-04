<?php

declare(strict_types=1);

namespace App\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContainerTypeRequest extends FormRequest
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
        return ContainerTypeRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ContainerTypeRules::messages();
    }
}
