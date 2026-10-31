<?php

declare(strict_types=1);

namespace App\Street\Payments\Http\Requests;

use App\Street\Payments\Enums\PaymentStatus;
use App\Street\Payments\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The admin cancels a payment of any day; the reason is mandatory because it is what the audit keeps.
 */
class CancelPaymentRequest extends FormRequest
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
                /** @var Payment $payment */
                $payment = $this->route('payment');

                if ($payment->status === PaymentStatus::Cancelled->value) {
                    $validator->errors()->add('reason', 'El cobro ya está anulado.');
                }
            },
        ];
    }
}
