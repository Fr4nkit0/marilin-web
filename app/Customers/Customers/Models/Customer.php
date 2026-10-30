<?php

namespace App\Customers\Customers\Models;

use App\Accounts\Models\Driver;
use App\Accounts\Models\User;
use App\Customers\Agreements\Models\CustomerAgreement;
use App\Customers\Customers\Database\Factories\CustomerFactory;
use App\Review\Reviewable;
use App\Review\ReviewSource;
use App\Routes\Routes\Models\RouteStop;
use App\Routes\Visits\Models\Visit;
use App\Street\Containers\Models\ContainerMovement;
use App\Street\Containers\Models\CustomerContainerBalance;
use App\Street\Ledger\Models\CustomerAccountEntry;
use App\Street\Ledger\Models\CustomerBalance;
use App\Street\Payments\Models\Payment;
use App\Street\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['driver_id', 'client_uuid', 'name', 'phone_number', 'created_by', 'last_modified_by', 'enabled', 'received_at', 'needs_review', 'review_reason', 'reviewed_at', 'reviewed_by'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use Reviewable;

    public static function reviewSource(): ReviewSource
    {
        return ReviewSource::customer('customer', 'updated_at');
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(CustomerPreference::class);
    }

    public function containerBalances(): HasMany
    {
        return $this->hasMany(CustomerContainerBalance::class);
    }

    public function containerMovements(): HasMany
    {
        return $this->hasMany(ContainerMovement::class);
    }

    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(CustomerBalance::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(CustomerAgreement::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
