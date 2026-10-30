<?php

declare(strict_types=1);

namespace App\Street\Sales\Http\Requests;

use App\Street\Containers\Enums\ContainerMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A sale at home: products and quantities only. Prices are never read from the request, the server resolves them.
 * A sale that is not on credit is paid on the spot, so it needs the payment method; a credit sale ignores it.
 */
class StoreHomeSaleRequest extends FormRequest
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
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('enabled', true)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_credit_sale' => ['sometimes', 'boolean'],
            'payment_method_id' => [Rule::excludeIf(fn (): bool => $this->boolean('is_credit_sale')), 'required', 'integer', Rule::exists('payment_methods', 'id')->where('enabled', true)],
            'notes' => ['nullable', 'string', 'max:500'],
            'containers' => ['sometimes', 'array'],
            'containers.*.container_type_id' => ['required', 'integer', Rule::exists('container_types', 'id')->where('enabled', true)],
            'containers.*.type' => ['required', Rule::in([ContainerMovementType::Delivered->value, ContainerMovementType::Returned->value])],
            'containers.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Cargá al menos un producto.',
            'lines.min' => 'Cargá al menos un producto.',
            'lines.*.product_id.distinct' => 'Un producto aparece dos veces en la venta.',
            'lines.*.product_id.exists' => 'Uno de los productos no existe o está deshabilitado.',
            'lines.*.quantity.integer' => 'Las cantidades van en unidades enteras.',
            'lines.*.quantity.min' => 'Las cantidades tienen que ser de al menos 1 unidad.',
            'payment_method_id.required' => 'Elegí el medio de pago o marcá la venta a crédito.',
            'payment_method_id.exists' => 'El método de pago no existe o está deshabilitado.',
            'containers.*.container_type_id.exists' => 'Uno de los envases no existe o está deshabilitado.',
            'containers.*.type.in' => 'El envase solo puede entregarse o devolverse.',
            'containers.*.quantity.integer' => 'Las cantidades de envases van en unidades enteras.',
            'containers.*.quantity.min' => 'Las cantidades de envases tienen que ser de al menos 1 unidad.',
        ];
    }
}
