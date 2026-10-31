<?php

declare(strict_types=1);

namespace App\Review\Http\Requests;

use App\Street\Sales\Models\SaleDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin fixes the truck load of a sale that left it below zero: only products the sale sold, whole units, and a mandatory reason.
 */
class CorrectTruckLoadRequest extends FormRequest
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
        $soldProducts = SaleDetail::query()->where('sale_id', (int) $this->route('id'))->pluck('product_id')->all();

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::in($soldProducts)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Corregí al menos un producto.',
            'items.*.product_id.distinct' => 'Un producto aparece dos veces en la corrección.',
            'items.*.product_id.in' => 'Solo se corrige la carga de los productos de la venta.',
            'items.*.quantity.integer' => 'Las cantidades van en unidades enteras.',
            'items.*.quantity.min' => 'Las cantidades tienen que ser de al menos 1 unidad.',
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
