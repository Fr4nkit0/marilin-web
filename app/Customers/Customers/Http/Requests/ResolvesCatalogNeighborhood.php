<?php

declare(strict_types=1);

namespace App\Customers\Customers\Http\Requests;

use App\Customers\Neighborhoods\Models\Neighborhood;

/**
 * A neighborhood typed in any case or without accents ("castanares grupo 200") becomes its catalog spelling
 * ("Castañares Grupo 200") before validation; a name outside the catalog stays as typed and is rejected.
 */
trait ResolvesCatalogNeighborhood
{
    protected function prepareForValidation(): void
    {
        $typed = $this->input('neighborhood');

        if (is_string($typed) && trim($typed) !== '') {
            $this->merge(['neighborhood' => Neighborhood::catalogName($typed) ?? $typed]);
        }
    }
}
