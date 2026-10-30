import type { ScheduledVisitStatus } from '../types';

const TINTS: Record<ScheduledVisitStatus, string> = {
    SCHEDULED: 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200/60 dark:border-amber-500/30',
    ON_ROUTE: 'bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30',
    VISITED: 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30',
    NOT_VISITED: 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200/60 dark:border-rose-500/30',
    CANCELLED: 'bg-surface-1 text-ink-muted border-hairline/60',
};

/** The status of a scheduled visit with the label the server derived from its stop. */
export default function ScheduledVisitStatusBadge({ status, label }: { status: ScheduledVisitStatus; label: string }) {
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${TINTS[status]}`}>
            {label}
        </span>
    );
}
