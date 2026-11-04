<?php

declare(strict_types=1);

namespace App\Catalog\Http\Requests;

use App\Catalog\Models\ContainerType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContainerTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A type may keep its own description, for instance to fix its letter case.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ContainerType $type */
        $type = $this->route('containerType');

        return ContainerTypeRules::rules($type);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ContainerTypeRules::messages();
    }
}
