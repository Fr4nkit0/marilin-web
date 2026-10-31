<?php

declare(strict_types=1);

namespace App\Street\Sales\Http\Requests;

use App\Street\Sales\Enums\SaleStatus;
use App\Street\Sales\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The admin cancels a sale of any day; the reason is mandatory because it is what the audit keeps.
 */
class CancelSaleRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Sale $sale */
                $sale = $this->route('sale');

                if ($sale->status === SaleStatus::Cancelled->value) {
                    $validator->errors()->add('reason', 'La venta ya está anulada.');
                }
            },
        ];
    }
}
