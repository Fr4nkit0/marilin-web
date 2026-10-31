<?php

declare(strict_types=1);

namespace App\Customers\Customers\Models;

use App\Accounts\Models\Driver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer changed driver: the old driver's app hides it on its next pull, the new one receives it whole.
 */
#[Fillable(['customer_id', 'from_driver_id', 'to_driver_id', 'reassigned_by', 'reassigned_at'])]
class CustomerReassignment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'reassigned_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'from_driver_id');
    }

    public function toDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'to_driver_id');
    }
}
