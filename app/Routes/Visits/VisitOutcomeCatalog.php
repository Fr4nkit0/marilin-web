<?php

declare(strict_types=1);

namespace App\Routes\Visits;

use App\Routes\Visits\Models\VisitOutcome;

/**
 * The admin's changes to the visit outcome catalog. The app relies on the seeded codes, so a code is never set
 * nor changed here: an outcome added by the admin has none. Nothing is deleted: a disabled outcome keeps reaching
 * the app, so the app hides it.
 */
final class VisitOutcomeCatalog
{
    public function add(string $name, bool $triggersReschedule): VisitOutcome
    {
        return VisitOutcome::query()->create([
            'code' => null,
            'name' => $name,
            'triggers_reschedule' => $triggersReschedule,
            'enabled' => true,
        ]);
    }

    public function edit(VisitOutcome $outcome, string $name, bool $triggersReschedule): void
    {
        $outcome->update(['name' => $name, 'triggers_reschedule' => $triggersReschedule]);
    }

    public function setEnabled(VisitOutcome $outcome, bool $enabled): void
    {
        $outcome->update(['enabled' => $enabled]);
    }
}
