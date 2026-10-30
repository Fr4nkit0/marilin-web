import { useEffect } from 'react';
import { CheckCircle2, X } from 'lucide-react';

/** A message to show; a new id restarts the toast even when the text repeats. */
export interface ToastMessage {
    id: number;
    text: string;
}

interface ToastProps {
    toast: ToastMessage | null;
    onDismiss: () => void;
    /** Milliseconds until it goes away by itself. */
    duration?: number;
}

/**
 * A short confirmation in the corner that goes away by itself. The live region is always there,
 * so screen readers announce a message as soon as it appears.
 */
export default function Toast({ toast, onDismiss, duration = 4000 }: ToastProps) {
    useEffect(() => {
        if (!toast) return;

        const timer = window.setTimeout(onDismiss, duration);
        return () => window.clearTimeout(timer);
        // The id, not the text, starts a new countdown: the same message twice shows twice.
    }, [toast?.id, duration]);

    return (
        <div role="status" aria-live="polite" className="pointer-events-none fixed bottom-4 right-4 z-[60] flex max-w-sm flex-col">
            {toast && (
                <div
                    key={toast.id}
                    className="pointer-events-auto flex items-start gap-3 rounded-xl border border-emerald-200 bg-canvas px-4 py-3 text-sm text-ink shadow-lg dark:border-emerald-500/30"
                >
                    <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-300" aria-hidden="true" />
                    <p className="flex-1">{toast.text}</p>
                    <button
                        type="button"
                        onClick={onDismiss}
                        aria-label="Cerrar aviso"
                        className="-m-1 rounded-md p-1 text-ink-muted hover:bg-surface-1 hover:text-ink focus:outline-none focus:ring-2 focus:ring-sky-600/40"
                    >
                        <X className="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            )}
        </div>
    );
}
