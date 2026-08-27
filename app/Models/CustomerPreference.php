<?php

namespace App\Models;

use Database\Factories\CustomerPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'day_of_week', 'time_window_start', 'time_window_end', 'created_by', 'last_modified_by', 'enabled'])]
class CustomerPreference extends Model
{
    /** @use HasFactory<CustomerPreferenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function scopeDay($query, int $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
