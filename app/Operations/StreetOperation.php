<?php

declare(strict_types=1);

namespace App\Operations;

use App\Enums\ContainerMovementType;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\ContainerMovement;
use App\Models\CustomerContainerBalance;
use App\Models\Payment;
use App\Models\Route;
use App\Models\Sale;
use App\Models\Transaction;
use App\Models\User;
use App\Review\ReviewReason;
use App\Services\CustomerLedger;
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

    public function __construct(
        private readonly CustomerLedger $ledger,
        private readonly RouteStock $stock,
    ) {}

    public function recordSale(RecordSale $input): Sale
    {
        return DB::transaction(function () use ($input): Sale {
            $sale = Sale::create([
                'client_uuid' => $input->clientUuid,
                'customer_id' => $input->customer->id,
                'visit_id' => $input->visit?->id,
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
            $this->discountStock($input, $sale);

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

    public function moveContainers(MoveContainers $input): ContainerMovement
    {
        $direction = self::DIRECTIONS[$input->type->value]
            ?? throw new InvalidArgumentException("Container movement type {$input->type->value} is not supported.");

        return DB::transaction(function () use ($input, $direction): ContainerMovement {
            $movement = ContainerMovement::create([
                'client_uuid' => $input->clientUuid,
                'customer_id' => $input->customer->id,
                'container_type_id' => $input->containerTypeId,
                'visit_id' => $input->visit?->id,
                'movement_type' => $input->type->value,
                'quantity' => $input->quantity,
                'direction' => $direction,
                'created_by' => $input->recordedBy->id,
                'enabled' => true,
                ...ReviewReason::columns($input->reviewReason),
            ]);

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
     */
    private function discountStock(RecordSale $input, Sale $sale): void
    {
        $routeId = $input->visit?->routeStop?->route_id
            ?? ($input->driver === null ? null : Route::query()
                ->where('assigned_driver_id', $input->driver->id)
                ->whereDate('date', $sale->date->toDateString())
                ->value('id'));

        if ($routeId === null) {
            return;
        }

        $this->stock->discount($routeId, $sale->details->groupBy('product_id')->map->sum('quantity')->all());
    }
}
