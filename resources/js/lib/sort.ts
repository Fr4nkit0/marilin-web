import { useState } from 'react';

export type SortDirection = 'asc' | 'desc';

export interface SortState<K extends string> {
    key: K | null;
    direction: SortDirection;
}

/** A value a column sorts by; null and undefined always go last, whatever the direction. */
export type SortValue = string | number | null | undefined;

/** The next sort after a click on a column header: the same column flips, another one starts ascending. */
export function nextSort<K extends string>(current: SortState<K>, key: K): SortState<K> {
    if (current.key === key) {
        return { key, direction: current.direction === 'asc' ? 'desc' : 'asc' };
    }

    return { key, direction: 'asc' };
}

// Spanish order that ignores case and accents and reads digits as numbers ("Ruta 2" before "Ruta 10").
const collator = new Intl.Collator('es', { sensitivity: 'base', numeric: true });

function compare(a: SortValue, b: SortValue): number {
    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }

    return collator.compare(String(a), String(b));
}

/** The rows ordered by one column, keeping the original order between equal values. */
export function sortRows<T>(rows: readonly T[], value: (row: T) => SortValue, direction: SortDirection): T[] {
    const sign = direction === 'asc' ? 1 : -1;

    return rows
        .map((row, index) => ({ row, index, value: value(row) }))
        .sort((a, b) => {
            const aMissing = a.value === null || a.value === undefined || a.value === '';
            const bMissing = b.value === null || b.value === undefined || b.value === '';
            if (aMissing || bMissing) {
                return aMissing === bMissing ? a.index - b.index : aMissing ? 1 : -1;
            }

            return sign * compare(a.value, b.value) || a.index - b.index;
        })
        .map(({ row }) => row);
}

/**
 * A table sorted in the browser, for lists the page already has whole (no pagination).
 * Until a header is clicked the rows keep the order the server sent.
 */
export function useSort<T, K extends string>(rows: readonly T[], columns: Record<K, (row: T) => SortValue>, initial: SortState<K> = { key: null, direction: 'asc' }) {
    const [sort, setSort] = useState<SortState<K>>(initial);

    // The lists are short (one day, one route), so sorting on every render costs nothing.
    const sorted = sort.key === null ? [...rows] : sortRows(rows, columns[sort.key], sort.direction);

    return {
        rows: sorted,
        sort,
        /** The direction a column shows, or null when the table is not sorted by it. */
        directionOf: (key: K): SortDirection | null => (sort.key === key ? sort.direction : null),
        toggle: (key: K) => setSort((current) => nextSort(current, key)),
    };
}
