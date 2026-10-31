<?php

declare(strict_types=1);

namespace App\Routes\Routes;

use App\Routes\Routes\Enums\RouteStatus;
use App\Routes\Routes\Models\Route;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use Illuminate\Support\Facades\DB;

/**
 * Writes a route's status from what the driver actually did on its stops: the first visit starts it,
 * the last done stop completes it and a not done stop added the same day reopens it. Cancelled routes are never touched.
 */
final class RouteProgress
{
    /**
     * Recalculates the route after a write to its stops or visits; call it inside that write's transaction.
     */
    public function recalculate(int $routeId): void
    {
        DB::transaction(function () use ($routeId): void {
            // NO KEY UPDATE: it serializes concurrent recalculations without conflicting with the key share lock
            // that inserting a stop of this route already holds, which FOR UPDATE would turn into a deadlock.
            $route = Route::query()->whereKey($routeId)->lock('for no key update')->first();

            if ($route === null || $route->status === RouteStatus::Cancelled->value) {
                return;
            }

            // A past day's route was closed by routes:close-past (or by its last stop): a late sync does not reopen it.
            if ($route->status === RouteStatus::Completed->value && $route->date->isBefore(today())) {
                return;
            }

            $arrivals = Visit::query()
                ->enabled()
                ->whereIn('route_stop_id', $route->stops()->select('id'))
                ->pluck('arrived_at');

            if ($arrivals->isEmpty()) {
                return;
            }

            $completed = $this->allStopsDone($route);

            $route->update([
                'status' => $completed ? RouteStatus::Completed->value : RouteStatus::InProgress->value,
                'started_at' => $route->started_at ?? $arrivals->min(),
                'completed_at' => $completed ? $arrivals->max() : null,
            ]);
        });
    }

    /**
     * Closes every route of a past day still planned or in progress: the day is over, whatever stops were left.
     * Those not done stops still go to the review inbox (review:flag-unvisited-stops).
     *
     * @return int how many routes were closed
     */
    public function closePastDays(): int
    {
        $open = Route::query()
            ->whereDate('date', '<', today())
            ->whereIn('status', [RouteStatus::Planned->value, RouteStatus::InProgress->value])
            ->pluck('id');

        $closed = 0;

        foreach ($open as $routeId) {
            DB::transaction(function () use ($routeId, &$closed): void {
                $route = Route::query()->whereKey($routeId)->lock('for no key update')->first();

                if ($route === null || ! in_array($route->status, [RouteStatus::Planned->value, RouteStatus::InProgress->value], true)) {
                    return;
                }

                $route->update([
                    'status' => RouteStatus::Completed->value,
                    'completed_at' => Visit::query()
                        ->enabled()
                        ->whereIn('route_stop_id', $route->stops()->select('id'))
                        ->max('arrived_at'),
                ]);

                $closed++;
            });
        }

        return $closed;
    }

    /**
     * Every enabled stop is done. A stop rescheduled within the day is done when its copy is, and the copy is a stop of the route itself.
     */
    private function allStopsDone(Route $route): bool
    {
        $stops = $route->activeStops()->with(['visit', 'rescheduledTo'])->get();

        return $stops->isNotEmpty()
            && $stops->every(fn (RouteStop $stop): bool => $stop->rescheduledTo !== null || $stop->isDone());
    }
}
