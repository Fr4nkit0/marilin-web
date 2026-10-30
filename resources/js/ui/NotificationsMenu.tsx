import { KeyboardEvent, useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Bell } from 'lucide-react';

export interface NotificationItem {
    key: string;
    label: string;
    href: string;
}

interface Props {
    items: NotificationItem[];
}

/** The header bell: a dot when there is something to see and a dropdown that links each item to its screen. */
export default function NotificationsMenu({ items }: Props) {
    const [open, setOpen] = useState(false);
    const container = useRef<HTMLDivElement>(null);
    const bell = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (!open) return;

        const closeOutside = (event: MouseEvent) => {
            if (!container.current?.contains(event.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', closeOutside);

        return () => document.removeEventListener('mousedown', closeOutside);
    }, [open]);

    const closeOnEscape = (event: KeyboardEvent) => {
        if (event.key !== 'Escape' || !open) return;
        setOpen(false);
        bell.current?.focus();
    };

    return (
        <div ref={container} className="relative" onKeyDown={closeOnEscape}>
            <button
                ref={bell}
                type="button"
                aria-label="Notificaciones"
                aria-haspopup="true"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="relative p-2 rounded-lg text-ink-muted hover:text-ink hover:bg-surface-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
            >
                <Bell className="w-5 h-5" strokeWidth={2} />
                {items.length > 0 && (
                    <span
                        data-testid="notifications-dot"
                        className="absolute top-2 right-2 w-2 h-2 bg-rose-500 rounded-full border-2 border-canvas"
                    ></span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-canvas rounded-xl shadow-lg border border-hairline/60 py-1.5 z-50">
                    <p className="px-4 py-2 text-xs font-semibold text-ink-muted">Notificaciones</p>
                    {items.length === 0 ? (
                        <p className="px-4 py-3 text-sm text-ink-muted">No hay novedades.</p>
                    ) : (
                        <ul>
                            {items.map((item) => (
                                <li key={item.key}>
                                    <Link
                                        href={item.href}
                                        onClick={() => setOpen(false)}
                                        className="block px-4 py-2.5 text-sm text-ink-secondary hover:bg-canvas-soft hover:text-ink focus:outline-none focus-visible:bg-canvas-soft focus-visible:text-ink"
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
