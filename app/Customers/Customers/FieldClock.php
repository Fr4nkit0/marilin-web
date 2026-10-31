<?php

declare(strict_types=1);

namespace App\Customers\Customers;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Last write wins per field: each synced field of a customer or an address keeps the moment it last changed in
 * `field_modified_at`. A field without its own entry dates from the creation of the row, never from its last update,
 * so a change to one field does not make an offline edit of another one lose.
 */
final class FieldClock
{
    /**
     * Applies each change strictly newer than its field's clock and moves that clock to `$modifiedAt`.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function merge(Model $row, array $changes, CarbonInterface $modifiedAt): FieldMerge
    {
        $applied = [];
        $outdated = [];

        foreach ($changes as $field => $value) {
            $clock = self::clockOf($row, $field);

            if ($clock !== null && $modifiedAt->lessThan($clock)) {
                $outdated[] = $field;

                continue;
            }

            if ($clock !== null && $modifiedAt->equalTo($clock)) {
                continue;
            }

            $row->setAttribute($field, $value);
            $applied[] = $field;
        }

        self::stamp($row, $applied, $modifiedAt);

        return new FieldMerge($applied, $outdated, count($changes));
    }

    /**
     * A web edit dates only the synced fields whose value it actually changed.
     *
     * @param  list<string>  $fields
     */
    public static function stampDirty(Model $row, array $fields, CarbonInterface $at): void
    {
        if ($row->exists) {
            self::stamp($row, array_values(array_intersect($fields, array_keys($row->getDirty()))), $at);
        }
    }

    /**
     * @param  list<string>  $fields
     */
    public static function stamp(Model $row, array $fields, CarbonInterface $at): void
    {
        if ($fields === []) {
            return;
        }

        $clocks = $row->getAttribute('field_modified_at') ?? [];

        foreach ($fields as $field) {
            $clocks[$field] = $at->format('Y-m-d\TH:i:s.uP');
        }

        $row->setAttribute('field_modified_at', $clocks);
    }

    private static function clockOf(Model $row, string $field): ?CarbonInterface
    {
        if (! $row->exists) {
            return null;
        }

        // Read raw: a row saved with its timestamps off no longer casts them.
        $clock = $row->getAttribute('field_modified_at')[$field] ?? $row->getAttributes()['created_at'] ?? null;

        return $clock === null ? null : Carbon::parse($clock);
    }
}
