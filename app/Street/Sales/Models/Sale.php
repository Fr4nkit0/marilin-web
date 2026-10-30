<?php

namespace App\Street\Sales\Models;

use App\Accounts\Models\User;
use App\Customers\Customers\Models\Customer;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use App\Routes\Routes\Models\Route;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Database\Factories\SaleFactory;
use App\Street\Sales\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['client_uuid', 'customer_id', 'visit_id', 'stock_route_id', 'date', 'total', 'status', 'is_credit_sale', 'notes', 'created_by', 'last_modified_by', 'enabled', 'needs_review', 'review_reason'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::userColumn('sale', 'date', 'created_by');
    }

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'total' => 'decimal:2',
            'is_credit_sale' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * The route the sale discounted its stock from; a cancellation gives it back there.
     */
    public function stockRoute(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'stock_route_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The containers moved with the sale, and their reversals once it is cancelled.
     */
    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', SaleStatus::Confirmed->value);
    }

    public function scopeCreditSales($query)
    {
        return $query->where('is_credit_sale', true);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
