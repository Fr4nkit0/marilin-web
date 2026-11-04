<?php

declare(strict_types=1);

namespace App\Routes\Visits\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitOutcomeRequest extends FormRequest
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
        return VisitOutcomeRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return VisitOutcomeRules::messages();
    }
}
