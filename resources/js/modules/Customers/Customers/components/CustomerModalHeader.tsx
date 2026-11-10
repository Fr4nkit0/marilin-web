import React from 'react';
import { X, type LucideIcon } from 'lucide-react';
import { ModalTitle } from '@/ui/Modal';

interface Props {
    icon: LucideIcon;
    title: string;
    description: string;
    onClose: () => void;
}

/** The top of the customer modals: what the form is for and a way out. */
export default function CustomerModalHeader({ icon: Icon, title, description, onClose }: Props) {
    return (
        <div className="shrink-0 flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-hairline">
            <div className="flex items-start gap-3">
                <div className="flex w-10 h-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                    <Icon className="w-5 h-5" aria-hidden="true" />
                </div>
                <div>
                    <ModalTitle className="text-lg font-semibold text-ink">{title}</ModalTitle>
                    <p className="text-sm text-ink-muted mt-0.5">{description}</p>
                </div>
            </div>
            <button
                type="button"
                onClick={onClose}
                aria-label="Cerrar"
                className="p-1.5 rounded-lg text-ink-subtle hover:text-ink-secondary hover:bg-surface-1 transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
            >
                <X className="w-5 h-5" />
            </button>
        </div>
    );
}
