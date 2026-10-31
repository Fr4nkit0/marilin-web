<?php

declare(strict_types=1);

namespace App\Review;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Payments\Models\Payment;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\Sales\Models\Sale;
use App\Street\StreetOperation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Everything the app uploaded or the system flagged that waits for an admin's eyes (ADR 0001), across every reviewable model.
 */
class ReviewInbox
{
    public const array TYPES = ['visit', 'sale', 'payment', 'route_stop', 'customer', 'container_movement', 'customer_agreement'];

    /**
     * The reviewable models, in the order the types are listed. Each one declares its own type, date and driver (Reviewable::reviewSource()).
     *
     * @var array<int, class-string<Model&Reviewable>>
     */
    private const array MODELS = [
        Visit::class,
        Sale::class,
        Payment::class,
        RouteStop::class,
        Customer::class,
        ContainerMovement::class,
        CustomerAgreement::class,
    ];

    public function __construct(
        private readonly StreetOperation $operation,
    ) {}

    /**
     * The drivers the admin can filter by.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function drivers(): Collection
    {
        return Driver::query()->with('user:id,name')->get()
            ->map(fn (Driver $driver): array => ['id' => $driver->id, 'name' => $driver->user->name])
            ->sortBy('name')
            ->values();
    }

    /**
     * The admin looked at a record: who and when are saved, nothing else about the record changes.
     *
     * @throws ModelNotFoundException when the record does not exist or is not waiting for review (a reviewed one keeps its first reviewer)
     */
    public function markReviewed(string $type, int $id, User $reviewer): void
    {
        $record = $this->modelOf($type)::query()->pendingReview()->findOrFail($id);

        $record->markReviewed($reviewer);
    }

    /**
     * The admin fixes the truck load a sale left below zero (the load was written down short): the missing units go
     * to the sale's route as a correction with the admin's reason, and the sale is marked reviewed, in one go.
     *
     * @param  array<int, int>  $quantities  quantity keyed by product id
     *
     * @throws ModelNotFoundException when the sale is not waiting for review because of the truck stock
     */
    public function correctTruckLoad(int $saleId, array $quantities, string $reason, User $admin): void
    {
        DB::transaction(function () use ($saleId, $quantities, $reason, $admin): void {
            $sale = Sale::query()->pendingReview()->whereNotNull('stock_route_id')->lockForUpdate()->findOrFail($saleId);

            if (! in_array(ReviewReason::InsufficientStock->value, $sale->pendingReviewReasons(), true)) {
                throw (new ModelNotFoundException)->setModel(Sale::class, [$saleId]);
            }

            $this->operation->correctRouteLoad($sale->stockRoute, $quantities, $reason, $admin);
            $sale->markReviewed($admin);
        });
    }

    /**
     * @param  array{driver_id?: int|null, from?: string|null, to?: string|null, type?: string|null, status?: string|null}  $filters
     */
    public function page(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $page = $this->items($filters)
            ->select('items.type', 'items.id', 'items.driver_id', 'items.occurred_at', 'items.reasons', 'items.reviewed_at')
            ->addSelect('customers.name as customer', 'users.name as driver', 'reviewers.name as reviewed_by')
            ->orderByDesc('items.occurred_at')
            ->orderBy('items.type')
            ->orderBy('items.id')
            ->paginate($perPage);

        $requestedStops = $this->requestedStops($page->items());
        $stockCorrections = $this->stockCorrections($page->items());

        return $page
            ->through(function (object $row) use ($requestedStops, $stockCorrections): object {
                $requested = $row->type === RouteStop::reviewSource()->type && isset($requestedStops[$row->id]);
                $row->stock_correction = $row->type === Sale::reviewSource()->type ? ($stockCorrections[$row->id] ?? null) : null;
                $row->reasons = ReviewReason::labels(json_decode((string) $row->reasons, true) ?? [], $requested);

                return $row;
            })
            ->withQueryString();
    }

    /**
     * The ids, as keys, of the page's stops that carried a scheduled visit.
     *
     * @param  array<int, object>  $rows
     * @return array<int, int>
     */
    private function requestedStops(array $rows): array
    {
        $stopIds = collect($rows)->where('type', RouteStop::reviewSource()->type)->pluck('id');

        return $stopIds->isEmpty() ? [] : RouteStop::query()
            ->whereIn('id', $stopIds)
            ->whereNotNull('scheduled_visit_id')
            ->pluck('id')
            ->flip()
            ->all();
    }

    /**
     * For each pending sale of the page that left the truck below zero, keyed by sale id: its route and the truck stock
     * of every product it sold, so the admin can correct the load from the inbox.
     *
     * @param  array<int, object>  $rows
     * @return array<int, array{route_id: int, products: list<array{id: int, name: string, current: float}>}>
     */
    private function stockCorrections(array $rows): array
    {
        $saleIds = collect($rows)
            ->where('type', Sale::reviewSource()->type)
            ->whereNull('reviewed_at')
            ->filter(fn (object $row): bool => in_array(ReviewReason::InsufficientStock->value, json_decode((string) $row->reasons, true) ?? [], true))
            ->pluck('id');

        if ($saleIds->isEmpty()) {
            return [];
        }

        $sales = Sale::query()->whereIn('id', $saleIds)->whereNotNull('stock_route_id')->with('details.product:id,name')->get();
        $stock = RouteInventory::query()
            ->whereIn('route_id', $sales->pluck('stock_route_id')->unique())
            ->get()
            ->keyBy(fn (RouteInventory $inventory): string => "{$inventory->route_id}-{$inventory->product_id}");

        return $sales->mapWithKeys(fn (Sale $sale): array => [$sale->id => [
            'route_id' => $sale->stock_route_id,
            'products' => $sale->details->unique('product_id')->sortBy('product_id')->map(fn ($detail): array => [
                'id' => $detail->product_id,
                'name' => $detail->product?->name ?? 'Producto',
                'current' => (float) ($stock->get("{$sale->stock_route_id}-{$detail->product_id}")?->current_quantity ?? 0),
            ])->values()->all(),
        ]])->all();
    }

    /**
     * How many records the inbox shows with no filter: everything still waiting for review, in one query.
     */
    public function pendingCount(): int
    {
        return $this->items([])->count();
    }

    /**
     * Every reviewable record that matches the filters, joined to its customer, driver and reviewer; pending only unless the status says otherwise.
     *
     * @param  array{driver_id?: int|null, from?: string|null, to?: string|null, type?: string|null, status?: string|null}  $filters
     */
    private function items(array $filters): Builder
    {
        $types = isset($filters['type']) ? [$filters['type']] : self::TYPES;
        $union = null;

        foreach ($types as $type) {
            $query = $this->modelOf($type)::reviewSource()->select((new ($this->modelOf($type)))->getTable());
            $union = $union === null ? $query : $union->unionAll($query);
        }

        return DB::query()
            ->fromSub($union, 'items')
            ->join('customers', 'customers.id', '=', 'items.customer_id')
            ->leftJoin('drivers', 'drivers.id', '=', 'items.driver_id')
            ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'items.reviewed_by')
            ->when($filters['driver_id'] ?? null, fn (Builder $q, $driverId) => $q->where('items.driver_id', $driverId))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('items.occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('items.occurred_at', '<=', $to))
            ->when(($filters['status'] ?? 'pending') === 'pending', fn (Builder $q) => $q->whereNull('items.reviewed_at'))
            ->when(($filters['status'] ?? null) === 'reviewed', fn (Builder $q) => $q->whereNotNull('items.reviewed_at'));
    }

    /**
     * @return class-string<Model&Reviewable>
     */
    private function modelOf(string $type): string
    {
        foreach (self::MODELS as $model) {
            if ($model::reviewSource()->type === $type) {
                return $model;
            }
        }

        throw new InvalidArgumentException("Unknown review type {$type}.");
    }
}
