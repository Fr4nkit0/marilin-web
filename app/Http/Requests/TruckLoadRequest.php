<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Route as DeliveryRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TruckLoadRequest extends FormRequest
{
    /**
     * A finished route already went through its cash close; loading it would change a closed day.
     */
    private const array CLOSED_STATUSES = ['COMPLETED', 'CANCELLED'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('enabled', true)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Cargá al menos un producto.',
            'items.*.product_id.distinct' => 'Un producto aparece dos veces en la carga.',
            'items.*.product_id.exists' => 'Uno de los productos no existe o está deshabilitado.',
            'items.*.quantity.integer' => 'Las cantidades van en unidades enteras.',
            'items.*.quantity.min' => 'Las cantidades tienen que ser de al menos 1 unidad.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DeliveryRoute $route */
                $route = $this->route('route');

                if (in_array($route->status, self::CLOSED_STATUSES, true)) {
                    $validator->errors()->add('items', 'La ruta ya está terminada y no admite más cargas.');
                }
            },
        ];
    }

    /**
     * @return array<int, int> quantity keyed by product id
     */
    public function quantities(): array
    {
        return collect($this->validated('items'))
            ->mapWithKeys(fn (array $item): array => [(int) $item['product_id'] => (int) $item['quantity']])
            ->all();
    }
}
