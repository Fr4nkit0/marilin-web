<?php

namespace App\Models;

use App\Accounts\Models\User;
use Database\Factories\VisitStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['visit_id', 'status', 'outcome_id', 'notes', 'created_by', 'last_modified_by', 'enabled'])]
class VisitStatusHistory extends Model
{
    /** @use HasFactory<VisitStatusHistoryFactory> */
    use HasFactory;

    protected $table = 'visit_status_histories';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(VisitOutcome::class, 'outcome_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
