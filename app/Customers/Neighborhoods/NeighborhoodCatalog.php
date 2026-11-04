<?php

declare(strict_types=1);

namespace App\Customers\Neighborhoods;

use App\Accounts\Models\User;
use App\Customers\Customers\FieldClock;
use App\Customers\Customers\Models\CustomerAddress;
use App\Customers\Neighborhoods\Models\Neighborhood;
use Illuminate\Support\Facades\DB;

/**
 * The admin's changes to the neighborhood catalog. Nothing is deleted: a neighborhood is disabled and keeps
 * reaching the app, so the app hides it.
 */
final class NeighborhoodCatalog
{
    public function add(string $name): Neighborhood
    {
        return Neighborhood::query()->create(['name' => $name, 'enabled' => true]);
    }

    /**
     * Addresses keep the neighborhood as text, so the ones written with the old name take the new one in the same
     * transaction. Each is a web edit of its `neighborhood` field: it moves the field's clock and its updated_at,
     * so the next pull sends it and an older offline edit from the app no longer wins.
     */
    public function rename(Neighborhood $neighborhood, string $name, User $by): void
    {
        DB::transaction(function () use ($neighborhood, $name, $by): void {
            $oldName = $neighborhood->name;
            $neighborhood->update(['name' => $name]);

            if ($oldName === $name) {
                return;
            }

            CustomerAddress::query()
                ->where('neighborhood', $oldName)
                ->lazyById()
                ->each(function (CustomerAddress $address) use ($name, $by): void {
                    $address->forceFill(['neighborhood' => $name, 'last_modified_by' => $by->id]);
                    FieldClock::stampDirty($address, CustomerAddress::SYNCED_FIELDS, now());
                    $address->save();
                });
        });
    }

    public function setEnabled(Neighborhood $neighborhood, bool $enabled): void
    {
        $neighborhood->update(['enabled' => $enabled]);
    }
}
