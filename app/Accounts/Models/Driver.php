<?php

namespace App\Accounts\Models;

use App\Accounts\Database\Factories\DriverFactory;
use App\Customers\Customers\Models\Customer;
use App\Routes\Routes\Models\Route;
use App\Routes\Visits\Models\Visit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'enabled'])]
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * The driver's app synced with the server (bootstrap, pull or push answered OK). A contact, not an edit of the driver,
     * so its update date stays.
     */
    public function markSynced(): void
    {
        $this->timestamps = false;
        $this->forceFill(['last_synced_at' => now()])->save();
        $this->timestamps = true;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'driver_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'assigned_driver_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'driver_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
