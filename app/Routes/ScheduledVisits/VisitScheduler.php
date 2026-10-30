<?php

declare(strict_types=1);

namespace App\Routes\ScheduledVisits;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\Enums\ScheduledVisitStatus;
use App\Routes\ScheduledVisits\Models\ScheduledVisit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes scheduled visits: the admin asks the customer's driver to come by on a date.
 * The visit rides on a stop of the driver's route that day, created by this class or carried by the customer's existing stop.
 */
final class VisitScheduler
{
    /**
     * Stores the visit and, when the driver's route that date exists or the date is today, puts it on the route at once;
     * otherwise routes:generate attaches it when it plans that date.
     *
     * @throws InvalidScheduledVisit
     */
    public function schedule(Customer $customer, CarbonInterface $date, ?string $time, ?string $note, User $by): ScheduledVisit
    {
        if ($customer->driver_id === null) {
            throw new InvalidScheduledVisit('Asigná un repartidor antes de agendar.');
        }

        if ($date->toDateString() < today()->toDateString()) {
            throw new InvalidScheduledVisit('La fecha no puede ser anterior a hoy.');
        }

        if (! $customer->enabled) {
            throw new InvalidScheduledVisit('El cliente está deshabilitado.');
        }

        if ($customer->primaryAddress()->enabled()->doesntExist()) {
            throw new InvalidScheduledVisit('El cliente no tiene una dirección principal.');
        }

        return DB::transaction(function () use ($customer, $date, $time, $note, $by): ScheduledVisit {
            $visit = ScheduledVisit::create([
                'customer_id' => $customer->id,
                'date' => $date->toDateString(),
                'scheduled_time' => $time === null ? null : Carbon::parse($time)->format('H:i:s'),
                'note' => $note,
                'status' => ScheduledVisitStatus::Scheduled->value,
                'created_by' => $by->id,
            ]);

            $route = Route::query()
                ->where('assigned_driver_id', $customer->driver_id)
                ->whereDate('date', $date->toDateString())
                ->first();

            if ($route === null && $date->isToday()) {
                $route = Route::create([
                    'assigned_driver_id' => $customer->driver_id,
                    'date' => $date->toDateString(),
                    'status' => RouteStatus::Planned->value,
                ]);
            }

            if ($route !== null) {
                $this->attach($visit, $route);
            }

            return $visit;
        });
    }

    /**
     * Puts the visit on the route: the customer's enabled, not done stop carries it (at the earliest of both times),
     * or a stop is created for it at the customer's primary address. Either way the stop changes, so the next pull sends it.
     */
    public function attach(ScheduledVisit $visit, Route $route): RouteStop
    {
        $stop = $route->stops()
            ->enabled()
            ->notDone()
            ->where('customer_id', $visit->customer_id)
            ->orderBy('id')
            ->first();

        $createdStop = $stop === null;

        if ($createdStop) {
            $stop = $route->stops()->create([
                'customer_id' => $visit->customer_id,
                'address_id' => $visit->customer->primaryAddress()->enabled()->firstOrFail()->id,
                'scheduled_time' => $visit->scheduled_time,
                'scheduled_visit_id' => $visit->id,
                'note' => $visit->note,
            ]);
        } else {
            $stop->update([
                'scheduled_visit_id' => $visit->id,
                'note' => $visit->note,
                'scheduled_time' => collect([$stop->scheduled_time, $visit->scheduled_time])->filter()->min(),
            ]);
        }

        $visit->update(['route_stop_id' => $stop->id, 'created_stop' => $createdStop]);

        return $stop;
    }

    /**
     * Cancels a visit not done yet: the stop created for it is disabled (the app drops it on pull),
     * a stop that carried it stays and loses the mark and the note.
     *
     * @throws InvalidScheduledVisit
     */
    public function cancel(ScheduledVisit $visit, User $by): void
    {
        if ($visit->isCancelled()) {
            throw new InvalidScheduledVisit('La visita ya está cancelada.');
        }

        $stop = $visit->routeStop;

        if ($stop !== null && $stop->visits()->exists()) {
            throw new InvalidScheduledVisit('La visita ya se hizo.');
        }

        DB::transaction(function () use ($visit, $stop, $by): void {
            $visit->update([
                'status' => ScheduledVisitStatus::Cancelled->value,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
            ]);

            if ($stop === null) {
                return;
            }

            $visit->created_stop
                ? $stop->update(['enabled' => false])
                : $stop->update(['scheduled_visit_id' => null, 'note' => null]);
        });
    }
}
