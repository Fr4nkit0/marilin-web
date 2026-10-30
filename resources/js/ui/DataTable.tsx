import type { ReactNode } from 'react';

export interface Column<T> {
    header: ReactNode;
    cell: (row: T) => ReactNode;
    align?: 'left' | 'center' | 'right';
    /** Extra classes for this column's cells. */
    className?: string;
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

/** A list's table: one header per column, one row per record, and a message when there are none. */
export default function DataTable<T>({ columns, rows, rowKey, empty }: Props<T>) {
    return (
        <div className="table-scroll">
            {/* Cells never wrap: on a narrow screen the table scrolls sideways instead of crushing its text columns. */}
            <table className="w-full text-left text-sm whitespace-nowrap">
                <thead className="bg-canvas-soft text-ink-muted font-mono uppercase tracking-wide text-xs border-b border-hairline">
                    <tr>
                        {columns.map((column, index) => (
                            <th key={index} className={`px-6 py-3.5 ${ALIGN[column.align ?? 'left']}`}>
                                {column.header}
                            </th>
                        ))}
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
                                    <td key={index} className={`px-6 py-4 ${ALIGN[column.align ?? 'left']} ${column.className ?? ''}`.trim()}>
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
