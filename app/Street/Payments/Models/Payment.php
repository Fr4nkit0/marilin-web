<?php

namespace App\Street\Payments\Models;

use App\Accounts\Models\User;
use App\Catalog\Models\PaymentMethod;
use App\Customers\Customers\Models\Customer;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use App\Routes\Visits\Models\Visit;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Payments\Database\Factories\PaymentFactory;
use App\Street\Payments\Enums\PaymentStatus;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_uuid', 'transaction_id', 'customer_id', 'visit_id', 'sale_id', 'payment_method_id', 'amount', 'received_by', 'paid_at', 'status', 'needs_review', 'review_reasons', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::userColumn('payment', 'paid_at', 'received_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', PaymentStatus::Confirmed->value);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', PaymentStatus::Cancelled->value);
    }
}
