<?php

namespace App\Customers\Neighborhoods\Models;

use App\Customers\Neighborhoods\Database\Factories\NeighborhoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Salta neighborhood the app offers for an address. Addresses keep the name as text, so a catalog row is never referenced by key.
 */
#[Fillable(['name', 'enabled'])]
class Neighborhood extends Model
{
    /** @use HasFactory<NeighborhoodFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
