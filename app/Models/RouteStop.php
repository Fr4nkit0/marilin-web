<?php

namespace App\Models;

use App\Enums\StopStatus;
use App\Enums\VisitStatus;
use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['route_id', 'customer_id', 'address_id', 'is_rescheduled_from', 'scheduled_time', 'client_uuid', 'enabled', 'needs_review', 'review_reason'])]
class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class, 'is_rescheduled_from');
    }

    public function rescheduledTo(): HasOne
    {
        return $this->hasOne(RouteStop::class, 'is_rescheduled_from');
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class)->latestOfMany();
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Status derived from the stop's visit and its reschedule link.
     */
    public function effectiveStatus(): StopStatus
    {
        if ($this->rescheduledTo !== null) {
            return StopStatus::Rescheduled;
        }

        return $this->visit === null ? StopStatus::Pending : StopStatus::from($this->visit->status);
    }

    /**
     * "Parada hecha": the visit finished, whatever its outcome (absent included).
     * A rescheduled stop is not done: the one left to do is the new one.
     */
    public function isDone(): bool
    {
        return $this->effectiveStatus() === StopStatus::Finished;
    }

    /**
     * Stops that are not done, with the same rule as isDone().
     */
    public function scopeNotDone($query)
    {
        return $query
            ->whereDoesntHave('rescheduledTo')
            ->whereDoesntHave('visit', fn ($visit) => $visit->where('status', VisitStatus::Finished->value));
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
