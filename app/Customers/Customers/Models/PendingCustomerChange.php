<?php

declare(strict_types=1);

namespace App\Customers\Customers\Models;

use App\Accounts\Models\User;
use App\Customers\Customers\Enums\CustomerChangeStatus;
use App\Customers\Customers\Enums\CustomerChangeTarget;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A driver's change to the data of a customer assigned to another driver (the customer, an address or a preference):
 * nothing is applied until the admin approves it from the review inbox (ADR 0001).
 */
#[Fillable(['customer_id', 'target', 'target_id', 'client_uuid', 'changes', 'modified_at', 'proposed_by', 'status', 'needs_review', 'review_reasons', 'reviewed_at', 'reviewed_by'])]
class PendingCustomerChange extends Model
{
    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::userColumn('customer_change', 'modified_at', 'proposed_by');
    }

    protected function casts(): array
    {
        return [
            'target' => CustomerChangeTarget::class,
            'status' => CustomerChangeStatus::class,
            'changes' => 'array',
            'modified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /**
     * The row it edits as it is now; null when it adds an address or a preference, or the row is gone.
     */
    public function targetRow(): Customer|CustomerAddress|CustomerPreference|null
    {
        return match ($this->target) {
            CustomerChangeTarget::Customer => $this->customer,
            CustomerChangeTarget::Address => $this->target_id === null ? null : CustomerAddress::query()->find($this->target_id),
            CustomerChangeTarget::Preference => $this->target_id === null ? null : CustomerPreference::query()->find($this->target_id),
        };
    }
}
