<?php

declare(strict_types=1);

namespace App\Operations\DailyFigures;

use App\Enums\PaymentStatus;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\Sale;

/**
 * The single source of a day's sales, collections and container movements, for the cash close,
 * the dashboard and the Sales and Payments pages.
 */
class DailyFigures
{
    public function forDay(string $date, DayScope $scope): DayFigures
    {
        $sales = Sale::query()
            ->with(['customer:id,name', 'creator:id,name'])
            ->tap(fn ($query) => $scope->apply($query, 'created_by'))
            ->whereDate('date', $date)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $payments = Payment::query()
            ->with(['customer:id,name', 'recipient:id,name', 'paymentMethod:id,code'])
            ->tap(fn ($query) => $scope->apply($query, 'received_by'))
            ->whereDate('paid_at', $date)
            ->where('status', PaymentStatus::Confirmed->value)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $containers = ContainerMovement::query()
            ->with(['customer:id,name', 'creator:id,name', 'containerType:id,description'])
            ->tap(fn ($query) => $scope->apply($query, 'created_by'))
            ->whereDate('created_at', $date)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return new DayFigures($sales, $payments, $containers);
    }
}
