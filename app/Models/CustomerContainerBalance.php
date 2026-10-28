<?php

namespace App\Models;

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use Database\Factories\CustomerContainerBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'container_type_id', 'last_movement_id', 'balance', 'created_by', 'last_modified_by', 'enabled'])]
class CustomerContainerBalance extends Model
{
    /** @use HasFactory<CustomerContainerBalanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }

    public function lastMovement(): BelongsTo
    {
        return $this->belongsTo(ContainerMovement::class, 'last_movement_id');
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
