<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['driver_id', 'name', 'phone_number', 'created_by', 'last_modified_by', 'enabled'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(CustomerPreference::class);
    }

    public function containerBalances(): HasMany
    {
        return $this->hasMany(CustomerContainerBalance::class);
    }

    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(CustomerBalance::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(CustomerAgreement::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
