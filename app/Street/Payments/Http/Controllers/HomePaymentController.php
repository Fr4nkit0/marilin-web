<?php

declare(strict_types=1);

namespace App\Street\Payments\Http\Controllers;

use App\Customers\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use App\Street\Payments\Http\Requests\StoreHomePaymentRequest;
use App\Street\Payments\RecordPayment;
use App\Street\StreetOperation;
use Illuminate\Http\RedirectResponse;

/**
 * Cobro en casa: a payment recorded by the administrator, with no visit and no sale.
 */
class HomePaymentController extends Controller
{
    public function store(StoreHomePaymentRequest $request, Customer $customer, StreetOperation $operation): RedirectResponse
    {
        $operation->recordPayment(new RecordPayment(
            customer: $customer,
            visit: null,
            sale: null,
            paymentMethodId: (int) $request->validated('payment_method_id'),
            amount: number_format((float) $request->validated('amount'), 2, '.', ''),
            paidAt: now(),
            receivedBy: $request->user(),
        ));

        return redirect()->route('customers.show', $customer)->with('success', 'Cobro en casa registrado.');
    }
}
