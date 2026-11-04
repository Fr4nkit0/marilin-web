<?php

declare(strict_types=1);

namespace App\Routes\Visits\Http\Requests;

use App\Routes\Visits\Models\VisitOutcome;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An outcome may keep its own name, for instance to fix its letter case.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var VisitOutcome $outcome */
        $outcome = $this->route('visitOutcome');

        return VisitOutcomeRules::rules($outcome);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return VisitOutcomeRules::messages();
    }
}
