<?php

namespace App\Models;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use Database\Factories\CustomerBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'current_balance', 'last_entry_id', 'created_by', 'last_modified_by', 'enabled'])]
class CustomerBalance extends Model
{
    /** @use HasFactory<CustomerBalanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lastEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerAccountEntry::class, 'last_entry_id');
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
