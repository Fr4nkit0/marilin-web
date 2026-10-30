<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Controllers;

use App\Customers\Customers\CustomerIntake;
use App\Customers\Customers\CustomerPreferenceChange;
use App\Customers\Customers\Http\Requests\SaveCustomerPreferenceRequest;
use App\Customers\Customers\InvalidCustomerChange;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerPreference;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The days and times a customer wants to be visited, edited from the customer page. Removing one disables it,
 * so the app learns about it in its next pull.
 */
class CustomerPreferenceController extends Controller
{
    public function store(SaveCustomerPreferenceRequest $request, Customer $customer, CustomerIntake $intake): RedirectResponse
    {
        $this->asFieldErrors(fn () => $intake->addPreference($customer, self::change($request->validated(), $request)));

        return redirect()->route('customers.show', $customer)->with('success', 'Día y horario agregado.');
    }

    public function update(SaveCustomerPreferenceRequest $request, Customer $customer, CustomerPreference $preference, CustomerIntake $intake): RedirectResponse
    {
        $this->asFieldErrors(fn () => $intake->savePreference($customer, $preference, self::change($request->validated(), $request)));

        return redirect()->route('customers.show', $customer)->with('success', 'Día y horario actualizado.');
    }

    public function destroy(Request $request, Customer $customer, CustomerPreference $preference, CustomerIntake $intake): RedirectResponse
    {
        $this->asFieldErrors(fn () => $intake->savePreference($customer, $preference, new CustomerPreferenceChange(
            dayOfWeek: $preference->day_of_week,
            timeWindowStart: $preference->time_window_start,
            timeWindowEnd: $preference->time_window_end,
            enabled: false,
            modifiedBy: $request->user(),
        )));

        return redirect()->route('customers.show', $customer)->with('success', 'Día y horario quitado.');
    }

    /**
     * A preference typed on the web (one row of a customer form, or the customer page's form).
     *
     * @param  array<string, mixed>  $row
     */
    public static function change(array $row, Request $request): CustomerPreferenceChange
    {
        return new CustomerPreferenceChange(
            dayOfWeek: (int) $row['day_of_week'],
            timeWindowStart: $row['time_window_start'] ?? null,
            timeWindowEnd: $row['time_window_end'] ?? null,
            enabled: true,
            modifiedBy: $request->user(),
        );
    }

    /**
     * Runs a change and shows a broken rule next to the field it is about.
     */
    public static function asFieldErrors(Closure $change): mixed
    {
        try {
            return $change();
        } catch (InvalidCustomerChange $invalid) {
            throw ValidationException::withMessages([$invalid->field ?? 'preference' => $invalid->getMessage()]);
        }
    }
}
