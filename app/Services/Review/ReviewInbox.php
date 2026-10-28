<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Models\Customer;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Visit;
use App\Review\Reviewable;
use App\Review\ReviewReason;
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
     * @param  array{driver_id?: int|null, from?: string|null, to?: string|null, type?: string|null, status?: string|null}  $filters
     */
    public function page(array $filters, int $perPage = 15): LengthAwarePaginator
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
