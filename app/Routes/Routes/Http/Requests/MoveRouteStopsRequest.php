<?php

declare(strict_types=1);

namespace App\Routes\Routes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveRouteStopsRequest extends FormRequest
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
            'stop_ids' => ['required', 'array', 'min:1'],
            'stop_ids.*' => ['required', 'integer', 'distinct'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stop_ids.required' => 'Elegí al menos una parada.',
            'stop_ids.min' => 'Elegí al menos una parada.',
            'driver_id.required' => 'Elegí un repartidor.',
            'driver_id.exists' => 'Elegí un repartidor habilitado.',
        ];
    }
}
