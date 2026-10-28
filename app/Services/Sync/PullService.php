<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\ContainerType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerAgreement;
use App\Models\CustomerBalance;
use App\Models\CustomerContainerBalance;
use App\Models\CustomerPreference;
use App\Models\Driver;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteInventory;
use App\Models\RouteStop;
use App\Models\VisitOutcome;
use Illuminate\Database\Eloquent\Builder;
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
     * so the moment the server received them also puts them in the window. Value: relation to the customer, or null.
     *
     * @var array<string, string|null>
     */
    private const array RECEIVED_AT_SOURCE = [
        'customers' => null,
        'customer_addresses' => 'customer',
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
            $query->where(function (Builder $q) use ($name, $cursor, $until): void {
                $inColumnWindow = fn (string $column) => fn (Builder $b) => $b
                    ->when($cursor, fn (Builder $w) => $w->where($column, '>=', $cursor))
                    ->where($column, '<=', $until);
                $inWindow = $inColumnWindow('updated_at');

                $q->where($inWindow);

                if (array_key_exists($name, self::RECEIVED_AT_SOURCE)) {
                    $relation = self::RECEIVED_AT_SOURCE[$name];

                    $relation === null
                        ? $q->orWhere($inColumnWindow('received_at'))
                        : $q->orWhereHas($relation, $inColumnWindow('received_at'));
                }

                foreach (self::DEPENDENT_RELATIONS[$name] ?? [] as $relation) {
                    $q->orWhereHas($relation, $inWindow);
                }

                if ($name === 'customer_agreements') {
                    // An agreement that ends inside the window changes its meaning without touching updated_at.
                    $q->orWhere(fn (Builder $b) => $b
                        ->whereNotNull('valid_to')
                        ->when($cursor, fn (Builder $w) => $w->where('valid_to', '>=', $cursor->toDateString()))
                        ->where('valid_to', '<=', $until->toDateString()));
                }
            })
                ->orderBy('updated_at')
                ->orderBy('id');

            if ($name === 'route_stops') {
                $query->with(['visit', 'rescheduledTo']);
            }

            $rows = $query->offset(($page - 1) * $size)->limit($size + 1)->get();

            $hasMore = $hasMore || $rows->count() > $size;
            $data[$name] = $rows->take($size)->values();

            if ($name === 'route_stops') {
                $data[$name] = $data[$name]->map(fn (RouteStop $stop): RouteStop => $this->withEffectiveStatus($stop));
            }
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
            'customer_addresses' => CustomerAddress::query()->whereHas('customer', $ownCustomer),
            'customer_preferences' => CustomerPreference::query()->whereHas('customer', $ownCustomer),
            'customer_agreements' => CustomerAgreement::query()->whereHas('customer', $ownCustomer),
            'customer_balances' => CustomerBalance::query()->whereHas('customer', $ownCustomer),
            'customer_container_balances' => CustomerContainerBalance::query()->whereHas('customer', $ownCustomer),
            'products' => Product::query(),
            'container_types' => ContainerType::query(),
            'payment_methods' => PaymentMethod::query(),
            'visit_outcomes' => VisitOutcome::query(),
        ];
    }

    private function withEffectiveStatus(RouteStop $stop): RouteStop
    {
        $stop->setAttribute('status', $stop->effectiveStatus()->value);
        $stop->unsetRelations();

        return $stop;
    }
}
