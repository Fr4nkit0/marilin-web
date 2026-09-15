<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uuid', 'entity_type', 'user_id'])]
class IdempotencyRecord extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
