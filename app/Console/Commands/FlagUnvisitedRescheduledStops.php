<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Review\NotDoneStopFlagger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('review:flag-rescheduled-stops')]
#[Description('Flag for review the rescheduled stops whose day ended not done (ADR 0002)')]
class FlagUnvisitedRescheduledStops extends Command
{
    public function handle(NotDoneStopFlagger $flagger): int
    {
        $flagged = $flagger->flagRescheduled();

        $this->info("Flagged {$flagged} rescheduled stop(s) for review.");

        return self::SUCCESS;
    }
}
