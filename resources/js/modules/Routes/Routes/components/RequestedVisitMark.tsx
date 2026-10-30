import { CalendarClock } from 'lucide-react';

/** "Visita solicitada" under the customer of a stop the admin scheduled, with the admin's note when there is one. */
export default function RequestedVisitMark({ note }: { note?: string | null }) {
    return (
        <div className="mt-1 space-y-0.5">
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium border bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30">
                <CalendarClock className="w-3 h-3" />
                Visita solicitada
            </span>
            {note && <p className="text-xs text-ink-muted whitespace-normal">{note}</p>}
        </div>
    );
}
