<?php

namespace App\Customers\Agreements\Models;

use App\Accounts\Models\User;
use App\Catalog\Models\Product;
use App\Customers\Agreements\Database\Factories\CustomerAgreementFactory;
use App\Customers\Customers\Models\Customer;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use App\Street\Sales\Models\SaleDetail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['customer_id', 'product_id', 'unit_price', 'valid_from', 'valid_to', 'reason', 'created_by', 'client_uuid', 'needs_review', 'review_reason'])]
class CustomerAgreement extends Model
{
    /** @use HasFactory<CustomerAgreementFactory> */
    use HasFactory;

    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::userColumn('customer_agreement', 'created_at', 'created_by');
    }

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    /**
     * The one validity rule: an agreement sets the price on a day from valid_from through valid_to, both included,
     * and with no valid_to it never ends. scopeActive() is this same rule in SQL; the tests pin both together.
     */
    public function isActiveOn(string $date): bool
    {
        return $this->valid_from->toDateString() <= $date
            && ($this->valid_to === null || $this->valid_to->toDateString() >= $date);
    }

    /**
     * Whether the agreement sets the price on the given day, has not started yet, or is over.
     *
     * @return 'active'|'upcoming'|'ended'
     */
    public function statusOn(string $date): string
    {
        return match (true) {
            $this->isActiveOn($date) => 'active',
            $this->valid_to !== null && $this->valid_to->toDateString() < $date => 'ended',
            default => 'upcoming',
        };
    }

    /**
     * Stops the agreement from today on: yesterday becomes its last day. Its price is never changed, so the
     * sales that used it keep their meaning; a new price is a new agreement.
     *
     * @throws ValidationException when it is already over
     */
    public function end(): void
    {
        $today = now()->toDateString();

        if ($this->statusOn($today) === 'ended') {
            throw ValidationException::withMessages(['agreement' => 'El acuerdo ya está finalizado.']);
        }

        $this->update(['valid_to' => now()->subDay()->toDateString()]);
    }

    /**
     * The validity rule of isActiveOn() as a query.
     */
    public function scopeActive($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            });
    }
}
