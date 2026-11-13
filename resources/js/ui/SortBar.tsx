import type { ReactNode } from 'react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import type { SortDirection } from '@/lib/sort';

export interface SortOption {
    label: string;
    /** The direction the list is sorted by this option, or null when another one sorts it. */
    direction: SortDirection | null;
    onSort: () => void;
}

const DIRECTION = { asc: 'ascendente', desc: 'descendente' } as const;

/**
 * The sortable columns as chips, for a table shown as cards on a phone (its headers are hidden there).
 * Each chip calls the same `onSort` as its header: once picks the column, again flips the direction.
 */
export default function SortBar({ options, className = '' }: { options: SortOption[]; className?: string }): ReactNode {
    if (options.length === 0) {
        return null;
    }

    return (
        <div role="group" aria-label="Ordenar por" className={`sm:hidden flex items-center gap-2 overflow-x-auto px-4 py-3 border-b border-hairline ${className}`.trim()}>
            <span aria-hidden="true" className="shrink-0 text-xs font-medium text-ink-muted">
                Ordenar por
            </span>
            {options.map((option) => {
                const Icon = option.direction === 'desc' ? ArrowDown : ArrowUp;

                return (
                    <button
                        key={option.label}
                        type="button"
                        onClick={option.onSort}
                        aria-pressed={option.direction !== null}
                        aria-label={option.direction ? `${option.label}, ${DIRECTION[option.direction]}` : option.label}
                        className={`shrink-0 inline-flex min-h-10 items-center gap-1 whitespace-nowrap rounded-full border px-3 text-xs font-medium transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 ${
                            option.direction
                                ? 'border-sky-600 bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
                                : 'border-hairline text-ink-secondary hover:bg-canvas-soft'
                        }`}
                    >
                        {option.label}
                        {option.direction && <Icon aria-hidden="true" className="w-3.5 h-3.5" />}
                    </button>
                );
            })}
        </div>
    );
}
