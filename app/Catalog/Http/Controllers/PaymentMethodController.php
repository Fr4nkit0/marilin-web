<?php

declare(strict_types=1);

namespace App\Catalog\Http\Controllers;

use App\Catalog\Http\Requests\PaymentMethodStatusRequest;
use App\Catalog\Http\Requests\UpdatePaymentMethodRequest;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\PaymentMethodCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The payment methods a driver or the admin records a payment with, managed from the settings section. There is
 * no create: the cash close counts by their fixed codes.
 */
class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Catalog/PaymentMethods', [
            'paymentMethods' => PaymentMethod::query()
                ->orderByDesc('enabled')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'enabled'])
                ->map(fn (PaymentMethod $method): array => [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'enabled' => $method->enabled,
                ]),
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod, PaymentMethodCatalog $catalog): RedirectResponse
    {
        $catalog->rename($paymentMethod, $request->validated('name'));

        return back()->with('success', 'Medio de pago actualizado');
    }

    public function status(PaymentMethodStatusRequest $request, PaymentMethod $paymentMethod, PaymentMethodCatalog $catalog): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $catalog->setEnabled($paymentMethod, $enabled);

        return back()->with('success', $enabled ? 'Medio de pago habilitado' : 'Medio de pago deshabilitado');
    }
}
