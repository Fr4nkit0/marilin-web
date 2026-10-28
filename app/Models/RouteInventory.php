<?php

namespace App\Models;

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use Database\Factories\RouteInventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['route_id', 'product_id', 'current_quantity', 'created_by', 'last_modified_by', 'enabled'])]
class RouteInventory extends Model
{
    /** @use HasFactory<RouteInventoryFactory> */
    use HasFactory;

    protected $table = 'route_inventories';

    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
