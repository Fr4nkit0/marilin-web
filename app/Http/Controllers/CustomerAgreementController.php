<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerAgreementRequest;
use App\Models\Customer;
use App\Models\CustomerAgreement;
use Illuminate\Http\RedirectResponse;

/**
 * Agreements are created and ended, never edited: a sale keeps pointing at the agreement whose price it used.
 */
class CustomerAgreementController extends Controller
{
    public function store(StoreCustomerAgreementRequest $request, Customer $customer): RedirectResponse
    {
        $customer->agreements()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('customers.show', $customer)->with('success', 'Acuerdo creado.');
    }

    public function end(Customer $customer, CustomerAgreement $agreement): RedirectResponse
    {
        $agreement->end();

        return redirect()->route('customers.show', $customer)->with('success', 'Acuerdo finalizado.');
    }
}
