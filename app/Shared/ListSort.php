<?php

declare(strict_types=1);

namespace App\Shared;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * The order a paginated list asks for in its query string (`?sort=name&direction=desc`).
 * Only the columns the list names can be sorted by, so the query string never reaches SQL as it is.
 */
final class ListSort
{
    /**
     * Orders the query by the requested column, with the key as a tiebreaker so pages never repeat or skip rows.
     * Returns the sort it applied, for the page to show, or null when the request asked for none or for an unknown
     * column or direction: then the query is left as it is and the list keeps its own default order.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  array<string, string|Closure(TBuilder, string): void>  $columns  sort key => column, or a closure that orders the query in the given direction
     * @return array{sort: string, direction: 'asc'|'desc'}|null
     */
    public static function apply(Builder $query, mixed $sort, mixed $direction, array $columns): ?array
    {
        if (! is_string($sort) || ! array_key_exists($sort, $columns) || ! in_array($direction, ['asc', 'desc'], true)) {
            return null;
        }

        $column = $columns[$sort];

        if ($column instanceof Closure) {
            $column($query, $direction);
        } else {
            $query->orderBy($column, $direction);
        }

        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        return ['sort' => $sort, 'direction' => $direction];
    }
}
