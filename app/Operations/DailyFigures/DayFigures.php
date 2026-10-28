<?php

declare(strict_types=1);

namespace App\Operations\DailyFigures;

use App\Catalog\Enums\PaymentMethodCode;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Collection;

/**
 * One day's money and container figures for a scope, with the records behind them in the order they happened.
 */
final readonly class DayFigures
{
    public float $totalSales;

    public float $creditSales;

    public float $cashAmount;

    public float $transferAmount;

    public float $totalCollected;

    public float $debtCollected;

    public int $containersDelivered;

    public int $containersReturned;

    /** @var Collection<int, Sale> */
    public Collection $creditSaleRows;

    /** @var Collection<int, Payment> */
    public Collection $cashPayments;

    /** @var Collection<int, Payment> */
    public Collection $transferPayments;

    /**
     * @param  Collection<int, Sale>  $sales
     * @param  Collection<int, Payment>  $payments  confirmed payments only
     * @param  Collection<int, ContainerMovement>  $containerMovements
     */
    public function __construct(
        public Collection $sales,
        public Collection $payments,
        public Collection $containerMovements,
    ) {
        $this->creditSaleRows = $sales->where('is_credit_sale', true);
        $this->cashPayments = $payments->filter(fn (Payment $payment): bool => $payment->paymentMethod?->code === PaymentMethodCode::Cash->value);
        $this->transferPayments = $payments->filter(fn (Payment $payment): bool => $payment->paymentMethod?->code === PaymentMethodCode::Transfer->value);

        $this->totalSales = (float) $sales->sum('total');
        $this->creditSales = (float) $this->creditSaleRows->sum('total');
        $this->cashAmount = (float) $this->cashPayments->sum('amount');
        $this->transferAmount = (float) $this->transferPayments->sum('amount');
        $this->totalCollected = $this->cashAmount + $this->transferAmount;
        $this->debtCollected = (float) $payments->whereNull('sale_id')->sum('amount');
        $this->containersDelivered = (int) $containerMovements->where('movement_type', ContainerMovementType::Delivered->value)->sum('quantity');
        $this->containersReturned = (int) $containerMovements->where('movement_type', ContainerMovementType::Returned->value)->sum('quantity');
    }
}
