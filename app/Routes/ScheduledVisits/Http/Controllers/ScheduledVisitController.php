<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits\Http\Controllers;

use App\Customers\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use App\Routes\ScheduledVisits\Http\Requests\StoreScheduledVisitRequest;
use App\Routes\ScheduledVisits\InvalidScheduledVisit;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use App\Routes\ScheduledVisits\VisitScheduler;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The admin asks the customer's driver to come by on a date, or takes it back, from the customer page.
 */
class ScheduledVisitController extends Controller
{
    public function store(StoreScheduledVisitRequest $request, Customer $customer, VisitScheduler $scheduler): RedirectResponse
    {
        $validated = $request->validated();

        // A rule about the customer (no driver, disabled, no address) shows above the form.
        $this->asFieldError(
            fn () => $scheduler->schedule(
                $customer,
                CarbonImmutable::parse($validated['date']),
                $validated['scheduled_time'] ?? null,
                $validated['note'] ?? null,
                $request->user(),
            ),
            'customer',
        );

        return redirect()->route('customers.show', $customer)->with('success', 'Visita agendada.');
    }

    public function cancel(Request $request, ScheduledVisit $scheduledVisit, VisitScheduler $scheduler): RedirectResponse
    {
        $this->asFieldError(fn () => $scheduler->cancel($scheduledVisit, $request->user()), 'scheduled_visit');

        return redirect()->route('customers.show', $scheduledVisit->customer_id)->with('success', 'Visita cancelada.');
    }

    /**
     * Shows a broken rule next to the field it names, or under `$otherwise` when it names none.
     */
    private function asFieldError(Closure $change, string $otherwise): void
    {
        try {
            $change();
        } catch (InvalidScheduledVisit $invalid) {
            throw ValidationException::withMessages([$invalid->field ?? $otherwise => $invalid->getMessage()]);
        }
    }
}
