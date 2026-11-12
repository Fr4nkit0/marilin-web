<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use App\Accounts\Models\Driver;
use App\Customers\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Part or all of a driver's portfolio going to another enabled driver.
 */
class TransferCustomersRequest extends FormRequest
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
            'to_driver_id' => [
                'bail',
                'required',
                'integer',
                Rule::notIn([$this->source()->id]),
                Rule::exists('drivers', 'id')->where('enabled', true),
            ],
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_driver_id.required' => 'Elegí el repartidor que recibe los clientes.',
            'to_driver_id.integer' => 'El repartidor de destino debe estar habilitado.',
            'to_driver_id.not_in' => 'Elegí un repartidor distinto del actual.',
            'to_driver_id.exists' => 'El repartidor de destino debe estar habilitado.',
            'customer_ids.required' => 'Elegí al menos un cliente.',
            'customer_ids.array' => 'Elegí al menos un cliente.',
            'customer_ids.min' => 'Elegí al menos un cliente.',
            'customer_ids.*.integer' => 'Algunos clientes elegidos ya no son de este repartidor.',
            'customer_ids.*.distinct' => 'Hay clientes repetidos en la selección.',
        ];
    }

    /**
     * Every selected customer is the source driver's right now.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('customer_ids*')) {
                    return;
                }

                $ids = array_values(array_unique(array_map('intval', (array) $this->input('customer_ids', []))));
                $owned = Customer::query()->whereKey($ids)->where('driver_id', $this->source()->id)->count();

                if ($owned !== count($ids)) {
                    $validator->errors()->add('customer_ids', 'Algunos clientes elegidos ya no son de este repartidor.');
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function customerIds(): array
    {
        return array_values(array_map('intval', $this->validated('customer_ids')));
    }

    public function source(): Driver
    {
        return $this->route('driver');
    }
}
