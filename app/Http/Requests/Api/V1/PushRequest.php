<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the envelope is validated here; each record is validated on its own so one invalid record never rejects the batch.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'customers' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function customers(): array
    {
        return array_values((array) $this->input('customers', []));
    }
}
