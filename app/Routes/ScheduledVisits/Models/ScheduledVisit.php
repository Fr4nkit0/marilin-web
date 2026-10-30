<?php

namespace App\Routes\ScheduledVisits\Models;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\ScheduledVisits\Database\Factories\ScheduledVisitFactory;
use App\Routes\ScheduledVisits\Enums\ScheduledVisitDisplayStatus;
use App\Routes\ScheduledVisits\Enums\ScheduledVisitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra visit the admin asked the customer's driver to make on a date: it becomes (or rides on) a stop of that day's route.
 */
#[Fillable(['customer_id', 'date', 'scheduled_time', 'note', 'status', 'route_stop_id', 'created_stop', 'created_by', 'cancelled_by', 'cancelled_at'])]
class ScheduledVisit extends Model
{
    /** @use HasFactory<ScheduledVisitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'created_stop' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === ScheduledVisitStatus::Cancelled->value;
    }

    /**
     * What the admin reads: the stored status while it has no stop, then what happened to its stop.
     */
    public function displayStatus(): ScheduledVisitDisplayStatus
    {
        if ($this->isCancelled()) {
            return ScheduledVisitDisplayStatus::Cancelled;
        }

        $stop = $this->routeStop;

        if ($stop === null) {
            return ScheduledVisitDisplayStatus::Scheduled;
        }

        if ($stop->visit !== null) {
            return ScheduledVisitDisplayStatus::Visited;
        }

        return $stop->route->date->isBefore(today())
            ? ScheduledVisitDisplayStatus::NotVisited
            : ScheduledVisitDisplayStatus::OnRoute;
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', ScheduledVisitStatus::Scheduled->value);
    }
}
