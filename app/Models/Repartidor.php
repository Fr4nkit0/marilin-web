<?php

namespace App\Models;

use Database\Factories\RepartidorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'enabled'])]
class Repartidor extends Model
{
    /** @use HasFactory<RepartidorFactory> */
    use HasFactory;

    protected $table = 'repartidores';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'repartidor_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'assigned_repartidor_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'repartidor_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
