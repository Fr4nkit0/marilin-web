import { ChevronLeft, ChevronRight } from 'lucide-react';

/** The day `days` away from a "YYYY-MM-DD" day, counted in UTC so a daylight-saving change never skips one. */
function shift(day: string, days: number): string {
    const next = new Date(`${day}T00:00:00Z`);
    next.setUTCDate(next.getUTCDate() + days);
    return next.toISOString().slice(0, 10);
}

const STEP =
    'flex w-11 h-11 sm:w-9 sm:h-9 shrink-0 items-center justify-center text-ink-muted hover:text-ink hover:bg-surface-1 transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sky-600';

/** A day to look at: a date field between "day before" and "day after" buttons. */
export default function DayPicker({ value, onChange }: { value: string; onChange: (day: string) => void }) {
    return (
        <div className="inline-flex items-center rounded-lg border border-hairline/60 bg-canvas shadow-sm overflow-hidden">
            <button type="button" aria-label="Día anterior" onClick={() => onChange(shift(value, -1))} className={STEP}>
                <ChevronLeft className="w-4 h-4" />
            </button>
            <input
                type="date"
                aria-label="Fecha"
                value={value}
                onChange={(e) => e.target.value && onChange(e.target.value)}
                className="border-y-0 border-x border-hairline/60 bg-transparent px-2 py-1.5 text-sm font-medium text-ink focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-600 cursor-pointer"
            />
            <button type="button" aria-label="Día siguiente" onClick={() => onChange(shift(value, 1))} className={STEP}>
                <ChevronRight className="w-4 h-4" />
            </button>
        </div>
    );
}
