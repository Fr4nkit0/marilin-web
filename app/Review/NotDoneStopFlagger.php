<?php

declare(strict_types=1);

namespace App\Review;

use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\RouteStop;

/**
 * The nightly flag: every enabled stop of a past, not cancelled route that ended its day not done is left for the admin (ADR 0001).
 * Planned and rescheduled stops differ only by the predicate and the reason.
 */
class NotDoneStopFlagger
{
    /**
     * @return int how many stops were flagged
     */
    public function flagPlanned(): int
    {
        return $this->flag(
            fn ($stops) => $stops->whereNull('is_rescheduled_from'),
            ReviewReason::UnvisitedStop,
        );
    }

    /**
     * @return int how many stops were flagged
     */
    public function flagRescheduled(): int
    {
        return $this->flag(
            fn ($stops) => $stops->whereNotNull('is_rescheduled_from'),
            ReviewReason::UnvisitedRescheduledStop,
        );
    }

    private function flag(callable $predicate, ReviewReason $reason): int
    {
        // A disabled stop (a cancelled scheduled visit) left the route: it was never due, so it is never unvisited.
        $stops = RouteStop::query()->unflagged()->enabled();
        $predicate($stops);

        return $stops
            ->whereHas('route', fn ($route) => $route->whereDate('date', '<', today())->where('status', '!=', RouteStatus::Cancelled->value))
            ->notDone()
            ->update(ReviewReason::columns($reason));
    }
}
