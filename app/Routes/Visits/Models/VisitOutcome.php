<?php

namespace App\Routes\Visits\Models;

use App\Routes\Visits\Database\Factories\VisitOutcomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'triggers_reschedule', 'enabled'])]
class VisitOutcome extends Model
{
    /** @use HasFactory<VisitOutcomeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'triggers_reschedule' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'outcome_id');
    }

    public function scopeRescheduleTriggers($query)
    {
        return $query->where('triggers_reschedule', true);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
