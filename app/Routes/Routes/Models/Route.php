<?php

namespace App\Routes\Routes\Models;

use App\Accounts\Models\Driver;
use App\Routes\Routes\Database\Factories\RouteFactory;
use App\Routes\Routes\Enums\RouteStatus;
use App\Street\RouteStock\Models\RouteInventory;
use App\Street\RouteStock\Models\RouteInventoryLoad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assigned_driver_id', 'date', 'status', 'started_at', 'completed_at', 'enabled'])]
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'enabled' => 'boolean',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }

    /**
     * The stops the driver still has to make or made: a disabled one (a cancelled scheduled visit) is gone from the route.
     * The web lists and counts these; sync keeps reading stops() so the app hears about the disabled ones.
     */
    public function activeStops(): HasMany
    {
        return $this->stops()->where('enabled', true);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(RouteInventory::class);
    }

    public function inventoryLoads(): HasMany
    {
        return $this->hasMany(RouteInventoryLoad::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', RouteStatus::Planned->value);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', RouteStatus::InProgress->value);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', RouteStatus::Completed->value);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
