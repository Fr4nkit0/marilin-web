<?php

declare(strict_types=1);

namespace App\Routes\Visits\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VisitOutcomeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled.required' => 'Indicá si el resultado queda habilitado.',
            'enabled.boolean' => 'Indicá si el resultado queda habilitado.',
        ];
    }
}
