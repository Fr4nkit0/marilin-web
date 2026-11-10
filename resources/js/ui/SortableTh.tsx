import type { ReactNode } from 'react';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { SortDirection } from '@/lib/sort';

interface Props {
    children: ReactNode;
    /** The direction the table is sorted by this column, or null when it is sorted by another one. */
    direction: SortDirection | null;
    onSort: () => void;
    align?: 'left' | 'center' | 'right';
    className?: string;
}

const JUSTIFY = { left: '', center: 'justify-center', right: 'justify-end' } as const;

/** A column header that sorts the table: the whole label is the button and the arrow tells the direction. */
export default function SortableTh({ children, direction, onSort, align = 'left', className = '' }: Props) {
    const Icon = direction === 'asc' ? ArrowUp : direction === 'desc' ? ArrowDown : ChevronsUpDown;

    return (
        <th
            scope="col"
            aria-sort={direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : 'none'}
            className={`${align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : ''} ${className}`.trim()}
        >
            <button
                type="button"
                onClick={onSort}
                className={`group inline-flex w-full items-center gap-1 ${JUSTIFY[align]} rounded-sm cursor-pointer select-none transition-colors hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 ${
                    direction ? 'text-ink' : ''
                }`}
            >
                {children}
                <Icon
                    aria-hidden="true"
                    className={`w-3.5 h-3.5 shrink-0 transition-opacity ${direction ? 'opacity-100' : 'opacity-40 group-hover:opacity-100'}`}
                />
            </button>
        </th>
    );
}
