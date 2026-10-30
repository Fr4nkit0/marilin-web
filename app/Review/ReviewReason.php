<?php

declare(strict_types=1);

namespace App\Review;

/**
 * Why a record the app uploaded was accepted but left for the admin to review (ADR 0001).
 */
enum ReviewReason: string
{
    case ForeignCustomer = 'Customer is assigned to another driver.';
    case DisabledCustomer = 'Customer is disabled.';
    case UnvisitedRescheduledStop = 'Rescheduled stop ended its day without a visit.';
    case UnvisitedStop = 'Stop ended its day without a visit.';
    case DriverAgreement = 'Agreement created by a driver.';
    case PriceMismatch = 'Sale price differs from the agreement or list price.';
    case DriverDisabledCustomer = 'Customer disabled by a driver.';
    case DriverCancellation = 'Cancelled by a driver.';

    /**
     * The columns that mark a record for review, or leave it clean when there is no reason: the one rule every writer of those columns follows.
     *
     * @return array{needs_review: bool, review_reason: string|null}
     */
    public static function columns(?self $reason): array
    {
        return ['needs_review' => $reason !== null, 'review_reason' => $reason?->value];
    }

    /**
     * What the admin reads in the review inbox; the backing value is what gets stored.
     * An unvisited stop that carried a scheduled visit reads as the requested visit it was.
     */
    public function label(bool $requestedStop = false): string
    {
        if ($requestedStop && in_array($this, [self::UnvisitedStop, self::UnvisitedRescheduledStop], true)) {
            return 'Visita solicitada sin visitar.';
        }

        return match ($this) {
            self::ForeignCustomer => 'El cliente está asignado a otro repartidor.',
            self::DisabledCustomer => 'El cliente está dado de baja.',
            self::UnvisitedRescheduledStop => 'La parada reprogramada terminó el día sin visita.',
            self::UnvisitedStop => 'La parada terminó el día sin visita.',
            self::DriverAgreement => 'Acuerdo de precio creado por el repartidor.',
            self::PriceMismatch => 'Venta con precio distinto al acordado o de lista.',
            self::DriverDisabledCustomer => 'El repartidor dio de baja al cliente.',
            self::DriverCancellation => 'Anulado por el repartidor.',
        };
    }
}
