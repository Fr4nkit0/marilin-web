<?php

namespace App\Models;

use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['route_id', 'customer_id', 'address_id', 'is_rescheduled_from', 'scheduled_time', 'enabled'])]
class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
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
    public function effectiveStatus(): string
    {
        if ($this->rescheduledTo !== null) {
            return 'RESCHEDULED';
        }

        return $this->visit->status ?? 'PENDING';
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
