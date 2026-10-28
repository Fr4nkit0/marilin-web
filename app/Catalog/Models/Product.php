<?php

namespace App\Catalog\Models;

use App\Catalog\Database\Factories\ProductFactory;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Models\RouteInventory;
use App\Models\RouteInventoryLoad;
use App\Models\SaleDetail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'price', 'requires_container', 'container_type_id', 'available_quantity', 'enabled'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'requires_container' => 'boolean',
            'available_quantity' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(CustomerAgreement::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function routeInventories(): HasMany
    {
        return $this->hasMany(RouteInventory::class);
    }

    public function routeInventoryLoads(): HasMany
    {
        return $this->hasMany(RouteInventoryLoad::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
