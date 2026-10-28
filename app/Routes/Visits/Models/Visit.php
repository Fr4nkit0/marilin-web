<?php

namespace App\Routes\Visits\Models;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Models\ContainerMovement;
use App\Models\Payment;
use App\Models\Sale;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Database\Factories\VisitFactory;
use App\Routes\Visits\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_uuid', 'route_stop_id', 'customer_id', 'driver_id', 'outcome_id', 'arrived_at', 'latitude', 'longitude', 'altitude', 'status', 'notes', 'created_by', 'last_modified_by', 'enabled', 'needs_review', 'review_reason'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::driverColumn('visit', 'arrived_at');
    }

    protected function casts(): array
    {
        return [
            'arrived_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'altitude' => 'decimal:7',
            'enabled' => 'boolean',
        ];
    }

    public function routeStop(): BelongsTo
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(VisitOutcome::class, 'outcome_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(VisitStatusHistory::class);
    }

    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', VisitStatus::Pending->value);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', VisitStatus::InProgress->value);
    }

    public function scopeFinished($query)
    {
        return $query->where('status', VisitStatus::Finished->value);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', VisitStatus::Cancelled->value);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
