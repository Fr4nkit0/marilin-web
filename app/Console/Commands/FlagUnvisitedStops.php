<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Review\NotDoneStopFlagger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('review:flag-unvisited-stops')]
#[Description('Flag for review the planned stops whose day ended not done')]
class FlagUnvisitedStops extends Command
{
    public function handle(NotDoneStopFlagger $flagger): int
    {
        // Rescheduled stops have their own task and reason (review:flag-rescheduled-stops).
        $flagged = $flagger->flagPlanned();

        $this->info("Flagged {$flagged} not done stop(s) for review.");

        return self::SUCCESS;
    }
}
