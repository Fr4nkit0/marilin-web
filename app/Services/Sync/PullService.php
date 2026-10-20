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
            $query->when($cursor, fn (Builder $q) => $q->where('updated_at', '>=', $cursor))
                ->where('updated_at', '<=', $until)
                ->orderBy('updated_at')
                ->orderBy('id');

            $rows = $query->offset(($page - 1) * $size)->limit($size + 1)->get();

            $hasMore = $hasMore || $rows->count() > $size;
            $data[$name] = $rows->take($size)->values();
        }

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $size,
                'is_last_page' => ! $hasMore,
                'until' => $until->toIso8601String(),
                'cursor' => $hasMore ? null : $until->toIso8601String(),
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
            'route_stops' => RouteStop::query()->whereHas('route', $ownRoute),
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
}
