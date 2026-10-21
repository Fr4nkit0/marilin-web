<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RouteStop;
use App\Services\Sync\ReviewReason;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('review:flag-unvisited-stops')]
#[Description('Flag for review the planned stops whose day ended without a visit')]
class FlagUnvisitedStops extends Command
{
    public function handle(): int
    {
        // Rescheduled stops have their own task and reason (review:flag-rescheduled-stops).
        $flagged = RouteStop::query()
            ->whereNull('is_rescheduled_from')
            ->where('enabled', true)
            ->where('needs_review', false)
            ->whereNull('reviewed_at')
            ->whereHas('route', fn ($route) => $route->whereDate('date', '<', today())->where('status', '!=', 'CANCELLED'))
            ->whereDoesntHave('visits')
            ->whereDoesntHave('rescheduledTo')
            ->update([
                'needs_review' => true,
                'review_reason' => ReviewReason::UnvisitedStop->value,
            ]);

        $this->info("Flagged {$flagged} unvisited stop(s) for review.");

        return self::SUCCESS;
    }
}
