<?php

namespace App\Models;

use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assigned_repartidor_id', 'date', 'status', 'started_at', 'completed_at', 'enabled'])]
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

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class, 'assigned_repartidor_id');
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
        return $query->where('status', 'PLANNED');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'IN_PROGRESS');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'COMPLETED');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
