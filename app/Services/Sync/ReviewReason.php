<?php

declare(strict_types=1);

namespace App\Services\Sync;

/**
 * Why a record the app uploaded was accepted but left for the admin to review (ADR 0001).
 */
enum ReviewReason: string
{
    case ForeignCustomer = 'Customer is assigned to another driver.';
    case DisabledCustomer = 'Customer is disabled.';
    case UnvisitedRescheduledStop = 'Rescheduled stop ended its day without a visit.';
    case UnvisitedStop = 'Stop ended its day without a visit.';

    /**
     * What the admin reads in the review inbox; the backing value is what gets stored.
     */
    public function label(): string
    {
        return match ($this) {
            self::ForeignCustomer => 'El cliente está asignado a otro repartidor.',
            self::DisabledCustomer => 'El cliente está dado de baja.',
            self::UnvisitedRescheduledStop => 'La parada reprogramada terminó el día sin visita.',
            self::UnvisitedStop => 'La parada terminó el día sin visita.',
        };
    }
}
