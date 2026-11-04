<?php

declare(strict_types=1);

namespace App\Customers\Neighborhoods\Http\Requests;

use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNeighborhoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A neighborhood may keep its own name, for instance to fix its letter case.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Neighborhood $neighborhood */
        $neighborhood = $this->route('neighborhood');

        return NeighborhoodNameRules::rules($neighborhood);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return NeighborhoodNameRules::messages();
    }
}
