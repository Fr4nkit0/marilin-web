<?php

namespace App\Street\Ledger\Models;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Street\Ledger\Database\Factories\CustomerAccountEntryFactory;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'entry_type', 'direction', 'amount', 'sale_id', 'payment_id', 'description', 'created_by'])]
class CustomerAccountEntry extends Model
{
    /** @use HasFactory<CustomerAccountEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSales($query)
    {
        return $query->where('entry_type', 'SALE');
    }

    public function scopePayments($query)
    {
        return $query->where('entry_type', 'PAYMENT');
    }

    public function scopeAdjustments($query)
    {
        return $query->where('entry_type', 'ADJUSTMENT');
    }
}
