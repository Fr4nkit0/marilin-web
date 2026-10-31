<?php

declare(strict_types=1);

namespace App\Review;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Where a reviewable model keeps its occurrence date and how to reach its driver, so the review inbox can select it without knowing the model.
 */
final readonly class ReviewSource
{
    private function __construct(
        public string $type,
        private string $occurredAt,
        private string $driver,
        private string $customer = 'customer_id',
        private bool $viaRoute = false,
        private bool $viaUser = false,
    ) {}

    /**
     * The model has a drivers.id column of its own.
     */
    public static function driverColumn(string $type, string $occurredAt, string $column = 'driver_id', string $customer = 'customer_id'): self
    {
        return new self($type, $occurredAt, $column, $customer);
    }

    /**
     * The model records the user that created or received it; its driver is the one that user is.
     */
    public static function userColumn(string $type, string $occurredAt, string $column): self
    {
        return new self($type, $occurredAt, $column, viaUser: true);
    }

    /**
     * The model belongs to a route, which has the date and the assigned driver.
     */
    public static function route(string $type): self
    {
        return new self($type, 'date', 'assigned_driver_id', viaRoute: true);
    }

    /**
     * Customers are their own customer; the date is the app's modification date.
     */
    public static function customer(string $type, string $occurredAt): self
    {
        return new self($type, $occurredAt, 'driver_id', 'id');
    }

    /**
     * One row per flagged record, with the same columns for every type so they can be unioned.
     */
    public function select(string $table): Builder
    {
        $query = DB::table($table)->where("{$table}.needs_review", true);

        if ($this->viaRoute) {
            $query->join('routes', 'routes.id', '=', "{$table}.route_id");
            $driverId = "routes.{$this->driver}";
            $occurredAt = "CAST(routes.{$this->occurredAt} AS timestamp)";
        } elseif ($this->viaUser) {
            $query->leftJoin('drivers', 'drivers.user_id', '=', "{$table}.{$this->driver}");
            $driverId = 'drivers.id';
            $occurredAt = "{$table}.{$this->occurredAt}";
        } else {
            $driverId = "{$table}.{$this->driver}";
            $occurredAt = "{$table}.{$this->occurredAt}";
        }

        return $query->selectRaw(
            "'{$this->type}' as type, {$table}.id, {$driverId} as driver_id, {$table}.{$this->customer} as customer_id, {$occurredAt} as occurred_at, {$table}.review_reasons as reasons, {$table}.reviewed_at, {$table}.reviewed_by"
        );
    }
}
