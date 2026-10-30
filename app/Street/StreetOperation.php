<?php

declare(strict_types=1);

namespace App\Street;

use App\Accounts\Models\User;
use App\Review\ReviewReason;
use App\Routes\Routes\Models\Route;
use App\Street\Containers\Enums\ContainerMovementType;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Containers\MoveContainers;
use App\Street\Ledger\CustomerLedger;
use App\Street\Payments\CancelPayment;
use App\Street\Payments\Enums\PaymentStatus;
use App\Street\Payments\Models\Payment;
use App\Street\Payments\Models\Transaction;
use App\Street\Payments\RecordPayment;
use App\Street\RouteStock\RouteStock;
use App\Street\Sales\CancelSale;
use App\Street\Sales\Enums\SaleStatus;
use App\Street\Sales\Models\Sale;
use App\Street\Sales\RecordSale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What happens on the street: sales, payments and container movements, plus loading the truck.
 * Owns the customer ledger, the route stock and the customer container balance, so each has a single writer.
 * Every operation is atomic on its own and also joins a surrounding transaction.
 */
class StreetOperation
{
    /**
     * Containers the customer holds go up on a delivery and down on a return; an adjustment is an initial balance.
     *
     * @var array<string, int>
     */
    private const array DIRECTIONS = [ContainerMovementType::Delivered->value => 1, ContainerMovementType::Returned->value => -1, ContainerMovementType::Adjustment->value => 1];

    /**
     * What gives back a movement of a cancelled sale.
     *
     * @var array<string, ContainerMovementType>
     */
    private const array OPPOSITES = [ContainerMovementType::Delivered->value => ContainerMovementType::Returned, ContainerMovementType::Returned->value => ContainerMovementType::Delivered];

    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly RouteStock $stock,
    ) {}

    public function recordSale(RecordSale $input): Sale
    {
        return DB::transaction(function () use ($input): Sale {
            $stockRouteId = $this->routeOfSale($input);

            $sale = Sale::create([
                'client_uuid' => $input->clientUuid,
                'customer_id' => $input->customer->id,
                'visit_id' => $input->visit?->id,
                'stock_route_id' => $stockRouteId,
                'date' => $input->date,
                'total' => $input->total,
                'status' => SaleStatus::Confirmed->value,
                'is_credit_sale' => $input->isCreditSale,
                'notes' => $input->notes,
                'created_by' => $input->recordedBy->id,
                'enabled' => true,
                ...ReviewReason::columns($input->reviewReason),
            ]);

            foreach ($input->lines as $line) {
                $sale->details()->create([
                    'product_id' => $line->productId,
                    'customer_agreement_id' => $line->agreement?->id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                ]);
            }

            $this->ledger->bookSale($input->customer, $sale, $input->recordedBy);

            if ($stockRouteId !== null) {
                $this->stock->discount($stockRouteId, $this->soldQuantities($sale), $input->recordedBy);
            }

            return $sale;
        });
    }

    /**
     * Cancels a sale: a credit note reverses it on the ledger, its stock goes back to the route it was discounted from
     * (none when it discounted none) and the containers moved with it go back with the opposite movement. Its payments are left alone.
     * A sale already cancelled is returned as it is, nothing is booked again.
     */
    public function cancelSale(CancelSale $input): Sale
    {
        return DB::transaction(function () use ($input): Sale {
            $sale = Sale::query()->whereKey($input->sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === SaleStatus::Cancelled->value) {
                return $sale;
            }

            $sale->fill(['status' => SaleStatus::Cancelled->value, 'last_modified_by' => $input->cancelledBy->id])->save();

            if ($input->reviewReason !== null) {
                $sale->reopenReview($input->reviewReason);
            }

            $this->ledger->bookSaleCancellation($sale->customer, $sale, $input->cancelledBy, $input->reason);

            if ($sale->stock_route_id !== null) {
                $this->stock->restore($sale->stock_route_id, $this->soldQuantities($sale));
            }

            $this->giveContainersBack($sale, $input->cancelledBy, $input->cancelledAt ?? now());

            return $sale;
        });
    }

    public function recordPayment(RecordPayment $input): Payment
    {
        return DB::transaction(function () use ($input): Payment {
            $transaction = Transaction::create(['type' => 'IN', 'amount' => $input->amount]);

            $payment = Payment::create([
                'client_uuid' => $input->clientUuid,
                'transaction_id' => $transaction->id,
                'customer_id' => $input->customer->id,
                'visit_id' => $input->visit?->id,
                'sale_id' => $input->sale?->id,
                'payment_method_id' => $input->paymentMethodId,
                'amount' => $input->amount,
                'received_by' => $input->receivedBy->id,
                'paid_at' => $input->paidAt,
                'status' => PaymentStatus::Confirmed->value,
                ...ReviewReason::columns($input->reviewReason),
            ]);

            $this->ledger->bookPayment($input->customer, $payment, $input->receivedBy);

            return $payment;
        });
    }

    /**
     * Cancels a payment: an adjustment puts its amount back on the ledger. A payment already cancelled is returned as it is.
     */
    public function cancelPayment(CancelPayment $input): Payment
    {
        return DB::transaction(function () use ($input): Payment {
            $payment = Payment::query()->whereKey($input->payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Cancelled->value) {
                return $payment;
            }

            $payment->fill(['status' => PaymentStatus::Cancelled->value])->save();

            if ($input->reviewReason !== null) {
                $payment->reopenReview($input->reviewReason);
            }

            $this->ledger->bookPaymentCancellation($payment->customer, $payment, $input->cancelledBy, $input->reason);

            return $payment;
        });
    }

    public function moveContainers(MoveContainers $input): ContainerMovement
    {
        return $this->recordContainerMovement($input);
    }

    /**
     * Every container movement goes through here, so the customer's balance always follows its movements.
     */
    private function recordContainerMovement(MoveContainers $input, ?ContainerMovement $reverses = null, ?CarbonInterface $at = null): ContainerMovement
    {
        $direction = self::DIRECTIONS[$input->type->value]
            ?? throw new InvalidArgumentException("Container movement type {$input->type->value} is not supported.");

        return DB::transaction(function () use ($input, $direction, $reverses, $at): ContainerMovement {
            $movement = new ContainerMovement([
                'client_uuid' => $input->clientUuid,
                'customer_id' => $input->customer->id,
                'container_type_id' => $input->containerTypeId,
                'visit_id' => $input->visit?->id,
                'sale_id' => $input->sale?->id,
                'reverses_movement_id' => $reverses?->id,
                'movement_type' => $input->type->value,
                'quantity' => $input->quantity,
                'direction' => $direction,
                'created_by' => $input->recordedBy->id,
                'enabled' => true,
                ...ReviewReason::columns($input->reviewReason),
            ]);

            if ($at !== null) {
                $movement->created_at = $at;
                $movement->updated_at = $at;
            }

            $movement->save();

            // Guarantees the row exists so the lock below always has something to lock when two first movements race.
            CustomerContainerBalance::query()->insertOrIgnore([
                'customer_id' => $input->customer->id,
                'container_type_id' => $input->containerTypeId,
                'balance' => 0,
                'created_by' => $input->recordedBy->id,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $balance = CustomerContainerBalance::query()
                ->where('customer_id', $input->customer->id)
                ->where('container_type_id', $input->containerTypeId)
                ->lockForUpdate()
                ->firstOrFail();

            $balance->fill([
                'balance' => $balance->balance + $input->quantity * $direction,
                'last_movement_id' => $movement->id,
                'last_modified_by' => $input->recordedBy->id,
            ])->save();

            return $movement;
        });
    }

    /**
     * The opposite movement (a delivery is returned, a return delivered again) for each container moved with a cancelled sale.
     * Reversals point at what they reverse, are never reversed themselves and are dated when the sale was cancelled.
     */
    private function giveContainersBack(Sale $sale, User $cancelledBy, CarbonInterface $cancelledAt): void
    {
        $movements = $sale->containerMovements()
            ->whereNull('reverses_movement_id')
            ->whereIn('movement_type', array_keys(self::OPPOSITES))
            ->with(['customer', 'visit'])
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $this->recordContainerMovement(new MoveContainers(
                customer: $movement->customer,
                containerTypeId: $movement->container_type_id,
                type: self::OPPOSITES[$movement->movement_type],
                quantity: $movement->quantity,
                visit: $movement->visit,
                recordedBy: $cancelledBy,
                sale: $sale,
            ), reverses: $movement, at: $cancelledAt);
        }
    }

    /**
     * Loads the truck of a route (the first load of the day or a reload).
     *
     * @param  array<int, int>  $quantities  quantity keyed by product id
     */
    public function loadRoute(Route $route, array $quantities, User $loadedBy): void
    {
        $this->stock->load($route, $quantities, $loadedBy);
    }

    /**
     * The route of the visit or, without visit, the driver's route of the sale's day; none without both.
     * Stored on the sale, so a cancellation gives back to the route the sale discounted from.
     */
    private function routeOfSale(RecordSale $input): ?int
    {
        return $input->visit?->routeStop?->route_id
            ?? ($input->driver === null ? null : Route::query()
                ->where('assigned_driver_id', $input->driver->id)
                ->whereDate('date', $input->date->toDateString())
                ->value('id'));
    }

    /**
     * @return array<int, int|float> quantity keyed by product id
     */
    private function soldQuantities(Sale $sale): array
    {
        return $sale->details->groupBy('product_id')->map->sum('quantity')->all();
    }
}
