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
    case PaymentExceedsDebt = "Payment exceeds the customer's debt.";
    case InsufficientStock = 'Sale leaves the truck stock below zero.';

    /**
     * The columns that mark a record for review with every reason given, or leave it clean when there is none:
     * the one rule every writer of those columns follows.
     *
     * @return array{needs_review: bool, review_reasons: list<string>}
     */
    public static function columns(?self ...$reasons): array
    {
        $values = self::values(...$reasons);

        return ['needs_review' => $values !== [], 'review_reasons' => $values];
    }

    /**
     * The stored values of the reasons given, in order, without repeats; a null reason (nothing to review) is left out.
     *
     * @return list<string>
     */
    public static function values(?self ...$reasons): array
    {
        return array_values(array_unique(array_map(
            fn (self $reason): string => $reason->value,
            array_filter($reasons, fn (?self $reason): bool => $reason !== null),
        )));
    }

    /**
     * What the admin reads for each stored reason; a value no case knows any more is shown as it was stored.
     *
     * @param  list<string>  $values
     * @return list<string>
     */
    public static function labels(array $values, bool $requestedStop = false): array
    {
        return array_map(fn (string $value): string => self::tryFrom($value)?->label($requestedStop) ?? $value, $values);
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
            self::PaymentExceedsDebt => 'El cobro supera la deuda del cliente y la venta del día.',
            self::InsufficientStock => 'La venta deja el stock del camión por debajo de cero.',
        };
    }
}
