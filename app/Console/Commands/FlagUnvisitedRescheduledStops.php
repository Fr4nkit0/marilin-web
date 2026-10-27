<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RouteStatus;
use App\Models\RouteStop;
use App\Services\Sync\ReviewReason;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('review:flag-rescheduled-stops')]
#[Description('Flag for review the rescheduled stops whose day ended not done (ADR 0002)')]
class FlagUnvisitedRescheduledStops extends Command
{
    public function handle(): int
    {
        $flagged = RouteStop::query()
            ->whereNotNull('is_rescheduled_from')
            ->where('needs_review', false)
            ->whereNull('reviewed_at')
            ->whereHas('route', fn ($route) => $route->whereDate('date', '<', today())->where('status', '!=', RouteStatus::Cancelled->value))
            ->notDone()
            ->update([
                'needs_review' => true,
                'review_reason' => ReviewReason::UnvisitedRescheduledStop->value,
            ]);

        $this->info("Flagged {$flagged} rescheduled stop(s) for review.");

        return self::SUCCESS;
    }
}
