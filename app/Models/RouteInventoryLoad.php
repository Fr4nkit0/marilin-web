<?php

namespace App\Models;

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Routes\Routes\Models\Route;
use Database\Factories\RouteInventoryLoadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['route_id', 'product_id', 'quantity', 'loaded_at', 'created_by'])]
class RouteInventoryLoad extends Model
{
    /** @use HasFactory<RouteInventoryLoadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'loaded_at' => 'datetime',
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
}
