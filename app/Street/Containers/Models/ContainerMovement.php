<?php

namespace App\Street\Containers\Models;

use App\Accounts\Models\User;
use App\Catalog\Models\ContainerType;
use App\Customers\Customers\Models\Customer;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Database\Factories\ContainerMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_uuid', 'customer_id', 'container_type_id', 'visit_id', 'movement_type', 'quantity', 'direction', 'reason', 'created_by', 'last_modified_by', 'enabled', 'needs_review', 'review_reason'])]
class ContainerMovement extends Model
{
    /** @use HasFactory<ContainerMovementFactory> */
    use HasFactory;

    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::userColumn('container_movement', 'created_at', 'created_by');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'direction' => 'integer',
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

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
