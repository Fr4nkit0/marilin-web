<?php

declare(strict_types=1);

namespace App\Review\Http\Controllers;

use App\Accounts\Models\User;
use App\Customers\Customers\ForeignCustomerChanges;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\PendingCustomerChange;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The admin's decision on a driver's change to another driver's customer (ADR 0001, exception).
 */
class CustomerChangeController extends Controller
{
    public function approve(Request $request, PendingCustomerChange $change, ForeignCustomerChanges $changes): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        try {
            $changes->approve($change, $admin);
        } catch (InvalidCustomerChange $invalid) {
            // It no longer fits the customer (e.g. its window overlaps a newer one): it stays pending, the admin reads why.
            return back()->withErrors(['change' => __($invalid->getMessage())]);
        }

        return back()->with('success', 'Cambio aprobado.');
    }

    public function reject(Request $request, PendingCustomerChange $change, ForeignCustomerChanges $changes): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $changes->reject($change, $admin);

        return back()->with('success', 'Cambio rechazado.');
    }
}
