<?php

declare(strict_types=1);

namespace App\CashDesk;

use App\Accounts\Models\Driver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Whose records a day's figures cover: everyone, one driver, or the house (Venta/Cobro en casa).
 *
 * A record belongs to a driver when it was made on one of their visits, or, without a visit, when the
 * driver's user recorded it. A record without visit recorded by anyone who is not a driver belongs to
 * the house. Every record belongs to exactly one driver or to the house.
 */
final readonly class DayScope
{
    public const HOUSE_FILTER = 'house';

    private function __construct(private ?Driver $driver, private bool $house) {}

    public static function all(): self
    {
        return new self(null, false);
    }

    public static function driver(Driver $driver): self
    {
        return new self($driver, false);
    }

    public static function house(): self
    {
        return new self(null, true);
    }

    /**
     * The scope a list filter asks for: empty is everyone, 'house' is the house, anything else a driver id.
     *
     * @throws ModelNotFoundException when the driver does not exist
     */
    public static function fromFilter(?string $filter): self
    {
        return match ($filter) {
            null, '' => self::all(),
            self::HOUSE_FILTER => self::house(),
            default => self::driver(Driver::query()->findOrFail((int) $filter)),
        };
    }

    /**
     * @param  Builder<*>  $query
     */
    public function apply(Builder $query, string $recordedByColumn): void
    {
        if ($this->driver !== null) {
            $driver = $this->driver;

            $query->where(fn (Builder $owned) => $owned
                ->whereHas('visit', fn (Builder $visit) => $visit->where('driver_id', $driver->id))
                ->orWhere(fn (Builder $loose) => $loose
                    ->whereNull('visit_id')
                    ->where($recordedByColumn, $driver->user_id)));
        } elseif ($this->house) {
            $query->whereNull('visit_id')
                ->whereNotIn($recordedByColumn, Driver::query()->select('user_id'));
        }
    }
}
