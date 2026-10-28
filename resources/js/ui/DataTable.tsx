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

const ALIGN = { left: '', center: 'text-center', right: 'text-right' } as const;

/** A list's table: one header per column, one row per record, and a message when there are none. */
export default function DataTable<T>({ columns, rows, rowKey, empty }: Props<T>) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead className="bg-slate-50/80 backdrop-blur-md text-slate-500 font-medium text-xs border-b border-gray-200/60">
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
                            <td colSpan={columns.length} className="px-6 py-8 text-center text-slate-500">
                                {empty}
                            </td>
                        </tr>
                    ) : (
                        rows.map((row) => (
                            <tr key={rowKey(row)} className="border-b border-gray-50 last:border-0 hover:bg-slate-50/50 transition-colors">
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
