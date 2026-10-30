<?php

declare(strict_types=1);

namespace App\Shared;

use Illuminate\Database\Eloquent\Builder;

/**
 * Text compared the way people type it: without minding case or accents ("martinez" is "Martínez").
 * Postgres does the comparison with the unaccent extension, so it works inside any query.
 */
final class TextMatch
{
    /**
     * Keeps the rows where any of the columns contains the term. A "relation.column" looks into a relation
     * (`primaryAddress.street`, `customer.name`). A blank term leaves the query as it is.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  list<string>  $columns
     * @return TBuilder
     */
    public static function search(Builder $query, array $columns, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // The term's own % and _ are plain characters, not wildcards.
        $pattern = '%'.addcslashes($term, '\\%_').'%';

        return $query->where(function (Builder $match) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $field] = explode('.', $column, 2);
                    $match->orWhereHas($relation, fn (Builder $related) => self::whereFolded($related, $field, 'like', $pattern));
                } else {
                    $match->orWhere(fn (Builder $own) => self::whereFolded($own, $column, 'like', $pattern));
                }
            }
        });
    }

    /**
     * Keeps the rows whose column is the value, minding neither case nor accents nor the spaces around it.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function equals(Builder $query, string $column, string $value): Builder
    {
        return self::whereFolded($query, $column, '=', trim($value));
    }

    private static function whereFolded(Builder $query, string $column, string $operator, string $value): Builder
    {
        $wrapped = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column));

        return $query->whereRaw("unaccent(lower({$wrapped})) {$operator} unaccent(lower(?))", [$value]);
    }
}
