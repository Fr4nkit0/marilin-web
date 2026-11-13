import type { ReactNode } from 'react';
import type { SortDirection } from '@/lib/sort';
import SortableTh from './SortableTh';
import SortBar from './SortBar';

export interface Column<T> {
    header: ReactNode;
    cell: (row: T) => ReactNode;
    align?: 'left' | 'center' | 'right';
    /** Extra classes for this column's cells. */
    className?: string;
    /** A sortable column: its header becomes a button that sorts the table by it. */
    sort?: { direction: SortDirection | null; onSort: () => void };
    /** The column's name on a phone card (and in the sort bar) when the header is not plain text. */
    label?: string;
    /** On a phone card this column is the title: shown first, bold and without a label. */
    primary?: boolean;
}

/** A column's plain-text name: its label, else its header when that is a string; none for a hidden header. */
export function columnLabel<T>(column: Column<T>): string | undefined {
    return column.label ?? (typeof column.header === 'string' ? column.header : undefined);
}

interface Props<T> {
    columns: Column<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** What to say when there are no rows. */
    empty: ReactNode;
}

// Numbers sit in right-aligned columns and use tabular figures so they line up (DESIGN.md).
const ALIGN = { left: '', center: 'text-center', right: 'text-right tabular-nums' } as const;

/**
 * A list's table: one header per column, one row per record, and a message when there are none.
 * On a phone each row is a card (`.table-cards` in app.css): every cell a "label: value" line, the primary
 * column as its title, and the sortable columns in a sort bar since the headers are hidden there.
 */
export default function DataTable<T>({ columns, rows, rowKey, empty }: Props<T>) {
    const sortOptions = columns.flatMap((column) => {
        const label = columnLabel(column);
        return column.sort && label ? [{ label, ...column.sort }] : [];
    });

    return (
        <div className="table-scroll">
            <SortBar options={sortOptions} />
            {/* Cells never wrap: from `sm` up the table scrolls sideways instead of crushing its text columns. */}
            <table className="table-cards w-full text-left text-sm whitespace-nowrap">
                <thead className="bg-canvas-soft text-ink-muted font-mono uppercase tracking-wide text-xs border-b border-hairline">
                    <tr>
                        {columns.map((column, index) =>
                            column.sort ? (
                                <SortableTh
                                    key={index}
                                    direction={column.sort.direction}
                                    onSort={column.sort.onSort}
                                    align={column.align}
                                    className="px-6 py-3.5"
                                >
                                    {column.header}
                                </SortableTh>
                            ) : (
                                <th key={index} className={`px-6 py-3.5 ${ALIGN[column.align ?? 'left']}`}>
                                    {column.header}
                                </th>
                            ),
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="px-6 py-8 text-center text-ink-muted">
                                <span className="table-empty">{empty}</span>
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={rowKey(row)} className="border-b border-hairline last:border-0 hover:bg-canvas-soft/60 transition-colors">
                                {columns.map((column, index) => (
                                    <td
                                        key={index}
                                        data-label={column.primary ? undefined : columnLabel(column)}
                                        data-primary={column.primary || undefined}
                                        className={`px-6 py-4 ${ALIGN[column.align ?? 'left']} ${column.className ?? ''}`.trim()}
                                    >
                                        {column.cell(row)}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
