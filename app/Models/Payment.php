<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_uuid', 'transaction_id', 'customer_id', 'visit_id', 'sale_id', 'payment_method_id', 'amount', 'received_by', 'paid_at', 'status', 'needs_review', 'review_reason'])]
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
