<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\ContainerMovement;
use App\Models\Customer;
use App\Models\CustomerAgreement;
use App\Models\Driver;
use App\Models\Payment;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\User;
use App\Models\Visit;
use App\Services\Sync\ReviewReason;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the app uploaded or the system flagged that waits for an admin's eyes (ADR 0001), across the four kinds of record.
 */
class ReviewInbox
{
    public const array TYPES = ['visit', 'sale', 'payment', 'route_stop', 'customer', 'container_movement', 'customer_agreement'];

    /**
     * @var array<string, class-string<Model>>
     */
    private const array MODELS = [
        'visit' => Visit::class,
        'sale' => Sale::class,
        'payment' => Payment::class,
        'route_stop' => RouteStop::class,
        'customer' => Customer::class,
        'container_movement' => ContainerMovement::class,
        'customer_agreement' => CustomerAgreement::class,
    ];

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
        /** @var Model $record */
        $record = self::MODELS[$type]::query()->where('needs_review', true)->whereNull('reviewed_at')->findOrFail($id);

        $record->forceFill(['reviewed_at' => now(), 'reviewed_by' => $reviewer->id])->save();
    }

    /**
     * @param  array{driver_id?: int|null, from?: string|null, to?: string|null, type?: string|null, status?: string|null}  $filters
     */
    public function page(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $types = isset($filters['type']) ? [$filters['type']] : self::TYPES;
        $union = null;

        foreach ($types as $type) {
            $query = match ($type) {
                'visit' => $this->selectVisit(),
                'sale' => $this->selectSale(),
                'payment' => $this->selectPayment(),
                'route_stop' => $this->selectRouteStop(),
                'customer' => $this->selectCustomer(),
                'container_movement' => $this->selectContainerMovement(),
                'customer_agreement' => $this->selectCustomerAgreement(),
            };
            $union = $union === null ? $query : $union->unionAll($query);
        }

        return DB::query()
            ->fromSub($union, 'items')
            ->join('customers', 'customers.id', '=', 'items.customer_id')
            ->leftJoin('drivers', 'drivers.id', '=', 'items.driver_id')
            ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'items.reviewed_by')
            ->select('items.type', 'items.id', 'items.driver_id', 'items.occurred_at', 'items.reason', 'items.reviewed_at')
            ->addSelect('customers.name as customer', 'users.name as driver', 'reviewers.name as reviewed_by')
            ->when($filters['driver_id'] ?? null, fn (Builder $q, $driverId) => $q->where('items.driver_id', $driverId))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('items.occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('items.occurred_at', '<=', $to))
            ->when(($filters['status'] ?? 'pending') === 'pending', fn (Builder $q) => $q->whereNull('items.reviewed_at'))
            ->when(($filters['status'] ?? null) === 'reviewed', fn (Builder $q) => $q->whereNotNull('items.reviewed_at'))
            ->orderByDesc('items.occurred_at')
            ->orderBy('items.type')
            ->orderBy('items.id')
            ->paginate($perPage)
            ->through(function (object $row): object {
                $row->reason = ReviewReason::tryFrom((string) $row->reason)?->label() ?? $row->reason;

                return $row;
            })
            ->withQueryString();
    }

    private function selectVisit(): Builder
    {
        return DB::table('visits')
            ->where('visits.needs_review', true)
            ->selectRaw("'visit' as type, visits.id, visits.driver_id, visits.customer_id, visits.arrived_at as occurred_at, visits.review_reason as reason, visits.reviewed_at, visits.reviewed_by");
    }

    private function selectSale(): Builder
    {
        return DB::table('sales')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'sales.created_by')
            ->where('sales.needs_review', true)
            ->selectRaw("'sale' as type, sales.id, drivers.id as driver_id, sales.customer_id, sales.date as occurred_at, sales.review_reason as reason, sales.reviewed_at, sales.reviewed_by");
    }

    private function selectPayment(): Builder
    {
        return DB::table('payments')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'payments.received_by')
            ->where('payments.needs_review', true)
            ->selectRaw("'payment' as type, payments.id, drivers.id as driver_id, payments.customer_id, payments.paid_at as occurred_at, payments.review_reason as reason, payments.reviewed_at, payments.reviewed_by");
    }

    private function selectRouteStop(): Builder
    {
        return DB::table('route_stops')
            ->join('routes', 'routes.id', '=', 'route_stops.route_id')
            ->where('route_stops.needs_review', true)
            ->selectRaw("'route_stop' as type, route_stops.id, routes.assigned_driver_id as driver_id, route_stops.customer_id, CAST(routes.date AS timestamp) as occurred_at, route_stops.review_reason as reason, route_stops.reviewed_at, route_stops.reviewed_by");
    }

    /**
     * A customer the app created or edited that belongs to another driver; the date is the app's modification date.
     */
    private function selectCustomer(): Builder
    {
        return DB::table('customers')
            ->where('customers.needs_review', true)
            ->selectRaw("'customer' as type, customers.id, customers.driver_id, customers.id as customer_id, customers.updated_at as occurred_at, customers.review_reason as reason, customers.reviewed_at, customers.reviewed_by");
    }

    /**
     * A movement carries no date of the app, so the moment the server received it is its date.
     */
    private function selectContainerMovement(): Builder
    {
        return DB::table('container_movements')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'container_movements.created_by')
            ->where('container_movements.needs_review', true)
            ->selectRaw("'container_movement' as type, container_movements.id, drivers.id as driver_id, container_movements.customer_id, container_movements.created_at as occurred_at, container_movements.review_reason as reason, container_movements.reviewed_at, container_movements.reviewed_by");
    }

    /**
     * A price agreement a driver created in the app; like a movement, the moment the server received it is its date.
     */
    private function selectCustomerAgreement(): Builder
    {
        return DB::table('customer_agreements')
            ->leftJoin('drivers', 'drivers.user_id', '=', 'customer_agreements.created_by')
            ->where('customer_agreements.needs_review', true)
            ->selectRaw("'customer_agreement' as type, customer_agreements.id, drivers.id as driver_id, customer_agreements.customer_id, customer_agreements.created_at as occurred_at, customer_agreements.review_reason as reason, customer_agreements.reviewed_at, customer_agreements.reviewed_by");
    }
}
