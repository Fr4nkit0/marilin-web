<?php

namespace App\Routes\Routes\Models;

use App\Accounts\Models\Driver;
use App\Models\RouteInventory;
use App\Models\RouteInventoryLoad;
use App\Routes\Routes\Database\Factories\RouteFactory;
use App\Routes\Routes\Enums\RouteStatus;
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
