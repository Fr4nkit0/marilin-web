<?php

namespace App\Customers\Customers\Models;

use App\Accounts\Models\User;
use App\Customers\Customers\Database\Factories\CustomerAddressFactory;
use App\Routes\Routes\Models\RouteStop;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'client_uuid', 'street', 'apartment', 'floor', 'visual_reference', 'neighborhood', 'latitude', 'longitude', 'is_primary', 'created_by', 'last_modified_by', 'enabled', 'received_at'])]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'floor' => 'string',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_primary' => 'boolean',
            'enabled' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'address_id');
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
