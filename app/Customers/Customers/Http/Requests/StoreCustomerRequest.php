<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use App\Catalog\Models\ContainerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
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
            'neighborhood' => ['nullable', 'string', Rule::exists('neighborhoods', 'name')->where('enabled', true)],
            'initial_balance' => 'nullable|numeric|min:0',
            // Keyed by container type id: how many containers of each type the customer already holds.
            'initial_containers' => 'nullable|array',
            'initial_containers.*' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $typeIds = array_keys((array) $this->input('initial_containers', []));
                $enabled = ContainerType::query()->enabled()->whereIn('id', array_filter($typeIds, 'is_int'))->count();

                if ($enabled !== count($typeIds)) {
                    $validator->errors()->add('initial_containers', 'Los envases iniciales deben ser de tipos de envase habilitados.');
                }
            },
        ];
    }
}
