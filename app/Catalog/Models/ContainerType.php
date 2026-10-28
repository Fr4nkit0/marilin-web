<?php

namespace App\Catalog\Models;

use App\Catalog\Database\Factories\ContainerTypeFactory;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description', 'enabled'])]
class ContainerType extends Model
{
    /** @use HasFactory<ContainerTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function customerBalances(): HasMany
    {
        return $this->hasMany(CustomerContainerBalance::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
