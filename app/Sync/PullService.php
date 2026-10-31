<?php

declare(strict_types=1);

namespace App\Sync;

use App\Accounts\Models\Driver;
use App\CashDesk\DayScope;
use App\Catalog\Models\ContainerType;
use App\Catalog\Models\PaymentMethod;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Customers\Models\CustomerPreference;
use App\Customers\Neighborhoods\Models\Neighborhood;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\VisitOutcome;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Enums\PaymentStatus;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Enums\SaleStatus;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PullService
{
    /**
     * Relations whose changes also make the parent row travel (e.g. a visit changes the stop status).
     *
     * @var array<string, array<int, string>>
     */
    private const array DEPENDENT_RELATIONS = [
        'route_stops' => ['visits', 'rescheduledTo'],
    ];

    /**
     * Entities pushed by the app carry the app's own modification date in updated_at, which can be hours old,
     * so the moment the server received them also puts them in the window. Value: where received_at is read, null for the row itself
     * or a relation (an address also travels when the customer push edited its customer).
     *
     * @var array<string, array<int, string|null>>
     */
    private const array RECEIVED_AT_SOURCE = [
        'customers' => [null],
        'customer_addresses' => [null, 'customer'],
        'customer_preferences' => [null],
    ];

    /**
     * Entities that belong to a customer: a customer reassigned to the driver inside the window arrives whole,
     * with rows that did not change themselves. Value: the relation to the customer, null for the customer itself.
     *
     * @var array<string, string|null>
     */
    private const array ARRIVE_WITH_CUSTOMER = [
        'customers' => null,
        'customer_addresses' => 'customer',
        'customer_preferences' => 'customer',
        'customer_agreements' => 'customer',
        'customer_balances' => 'customer',
        'customer_container_balances' => 'customer',
    ];

    /**
     * Cancellation events: the id key each one carries. They travel when the sale or payment was cancelled (or touched
     * afterwards) inside the window; applying one twice is harmless, so the app marks the record cancelled every time.
     *
     * @var array<string, string>
     */
    private const array CANCELLATION_KEYS = [
        'sale_cancellations' => 'sale_id',
        'payment_cancellations' => 'payment_id',
    ];

    /**
     * Returns one page of everything that changed in (cursor, until], disabled records included.
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function page(Driver $driver, ?Carbon $cursor, Carbon $until, int $page): array
    {
        $size = (int) config('sync.pull_page_size');
        $hasMore = false;
        $data = [];

        foreach ($this->entities($driver) as $name => $query) {
            $name === 'customers_removed'
                ? $this->whereLeftDriver($query, $driver, $cursor, $until)
                : $this->whereChanged($query, $name, $driver, $cursor, $until);

            $query
                ->orderBy('updated_at')
                ->orderBy('id');

            if ($name === 'route_stops') {
                $query->with(['visit', 'rescheduledTo']);
            }

            $rows = $query->offset(($page - 1) * $size)->limit($size + 1)->get();

            $hasMore = $hasMore || $rows->count() > $size;
            $data[$name] = $rows->take($size)->values();

            if ($name === 'route_stops') {
                $data[$name] = $data[$name]->map(fn (RouteStop $stop): RouteStop => $this->withDerivedFields($stop));
            }

            $data[$name] = match (true) {
                isset(self::CANCELLATION_KEYS[$name]) => $data[$name]->map(fn (Sale|Payment $record): array => $this->cancellationEvent($record, self::CANCELLATION_KEYS[$name], $driver)),
                $name === 'customers_removed' => $data[$name]->map(fn (Customer $customer): array => $this->removal($customer)),
                default => $data[$name]->map(fn (Model $row): array => $this->row($row)),
            };
        }

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $size,
                'is_last_page' => ! $hasMore,
                'until' => $until->toIso8601String(),
                'cursor' => $hasMore ? null : $until->copy()->subSeconds((int) config('sync.pull_cursor_margin_seconds'))->toIso8601String(),
            ],
        ];
    }

    /**
     * The rows that changed inside the window, or that travel because something they depend on did.
     *
     * @param  Builder<*>  $query
     */
    private function whereChanged(Builder $query, string $name, Driver $driver, ?Carbon $cursor, Carbon $until): void
    {
        $inColumnWindow = fn (string $column) => fn (Builder $b) => $b
            ->when($cursor, fn (Builder $w) => $w->where($column, '>=', $cursor))
            ->where($column, '<=', $until);

        $query->where(function (Builder $q) use ($name, $driver, $cursor, $until, $inColumnWindow): void {
            $inWindow = $inColumnWindow('updated_at');

            $q->where($inWindow);

            foreach (self::RECEIVED_AT_SOURCE[$name] ?? [] as $relation) {
                $relation === null
                    ? $q->orWhere($inColumnWindow('received_at'))
                    : $q->orWhereHas($relation, $inColumnWindow('received_at'));
            }

            foreach (self::DEPENDENT_RELATIONS[$name] ?? [] as $relation) {
                $q->orWhereHas($relation, $inWindow);
            }

            if (array_key_exists($name, self::ARRIVE_WITH_CUSTOMER)) {
                $reassignedHere = fn (Builder $b) => $b->whereHas('reassignments', fn (Builder $r) => $r
                    ->where('to_driver_id', $driver->id)
                    ->where($inColumnWindow('reassigned_at')));
                $relation = self::ARRIVE_WITH_CUSTOMER[$name];

                $relation === null ? $q->orWhere($reassignedHere) : $q->orWhereHas($relation, $reassignedHere);
            }

            if ($name === 'customer_agreements') {
                // An agreement that ends inside the window changes its meaning without touching updated_at.
                $q->orWhere(fn (Builder $b) => $b
                    ->whereNotNull('valid_to')
                    ->when($cursor, fn (Builder $w) => $w->where('valid_to', '>=', $cursor->toDateString()))
                    ->where('valid_to', '<=', $until->toDateString()));
            }
        });
    }

    /**
     * The customers that left the driver inside the window (reassigned to another driver or to none) and are not
     * theirs again by its end. Only a pull with a cursor carries them: a first pull has nothing to hide.
     *
     * @param  Builder<Customer>  $query
     */
    private function whereLeftDriver(Builder $query, Driver $driver, ?Carbon $cursor, Carbon $until): void
    {
        if ($cursor === null) {
            $query->whereRaw('false');

            return;
        }

        $leftInWindow = fn (Builder $r) => $r
            ->where('from_driver_id', $driver->id)
            ->where('reassigned_at', '>=', $cursor)
            ->where('reassigned_at', '<=', $until);

        $query
            ->where(fn (Builder $q) => $q->whereNull('driver_id')->orWhere('driver_id', '!=', $driver->id))
            ->whereHas('reassignments', $leftInWindow)
            ->withMax(['reassignments as removed_at' => $leftInWindow], 'reassigned_at');
    }

    /**
     * What the app needs to hide a customer that left the driver, keeping its history: the server id, its own uuid
     * and when it left (the last time inside the window).
     *
     * @return array{customer_id: int, client_uuid: string|null, removed_at: string}
     */
    private function removal(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'client_uuid' => $customer->client_uuid,
            'removed_at' => Carbon::parse($customer->getAttribute('removed_at'))->toJSON(),
        ];
    }

    /**
     * One row as the pull sends it; the push answers a conflict with the server row in this very shape.
     *
     * @return array<string, mixed>
     */
    public function row(Model $row): array
    {
        return $row->toArray();
    }

    /**
     * @return array<string, Builder<*>>
     */
    private function entities(Driver $driver): array
    {
        $ownCustomer = fn (Builder $query) => $query->where('driver_id', $driver->id);
        $ownRoute = fn (Builder $query) => $query->where('assigned_driver_id', $driver->id);

        return [
            'routes' => Route::query()->where('assigned_driver_id', $driver->id),
            'route_stops' => RouteStop::query()
                ->whereHas('route', $ownRoute)
                ->whereHas('customer', fn (Builder $query) => $query->where('driver_id', $driver->id)->where('enabled', true)),
            'route_inventories' => RouteInventory::query()->whereHas('route', $ownRoute),
            'customers' => Customer::query()->where('driver_id', $driver->id),
            'customers_removed' => Customer::query(),
            'customer_addresses' => CustomerAddress::query()->whereHas('customer', $ownCustomer),
            'customer_preferences' => CustomerPreference::query()->whereHas('customer', $ownCustomer),
            'customer_agreements' => CustomerAgreement::query()->whereHas('customer', $ownCustomer),
            'customer_balances' => CustomerBalance::query()->whereHas('customer', $ownCustomer),
            'customer_container_balances' => CustomerContainerBalance::query()->whereHas('customer', $ownCustomer),
            // The driver's own sales and payments, as the cash close counts them: on their visits or recorded by them without one.
            'sale_cancellations' => Sale::query()
                ->where('status', SaleStatus::Cancelled->value)
                ->tap(fn (Builder $query) => DayScope::driver($driver)->apply($query, 'created_by')),
            'payment_cancellations' => Payment::query()
                ->where('status', PaymentStatus::Cancelled->value)
                ->tap(fn (Builder $query) => DayScope::driver($driver)->apply($query, 'received_by')),
            'products' => Product::query(),
            'container_types' => ContainerType::query(),
            'payment_methods' => PaymentMethod::query(),
            'visit_outcomes' => VisitOutcome::query(),
            'neighborhoods' => Neighborhood::query(),
        ];
    }

    /**
     * What the app needs to mark its record cancelled: the server id, its own uuid, when, why and whether the admin
     * (anyone but the driver) cancelled it. The stock, balance and containers it gave back travel in their own entities.
     *
     * @return array{sale_id?: int, payment_id?: int, client_uuid: string|null, cancelled_at: string|null, reason: string|null, cancelled_by_admin: bool}
     */
    private function cancellationEvent(Sale|Payment $record, string $idKey, Driver $driver): array
    {
        return [
            $idKey => $record->id,
            'client_uuid' => $record->client_uuid,
            'cancelled_at' => $record->cancelled_at?->toJSON(),
            'reason' => $record->cancellation_reason,
            'cancelled_by_admin' => $record->cancelled_by !== $driver->user_id,
        ];
    }

    private function withDerivedFields(RouteStop $stop): RouteStop
    {
        $stop->setAttribute('status', $stop->effectiveStatus()->value);
        $stop->setAttribute('is_requested', $stop->isRequested());
        $stop->unsetRelations();

        return $stop;
    }
}
