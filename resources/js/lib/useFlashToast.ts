import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { ToastMessage } from '@/ui/Toast';

/**
 * The success message the server flashed with the last visit, as a toast. Every visit brings a new flash object,
 * so the same text after a second action shows again; a partial reload keeps the old object and shows nothing new.
 */
export function useFlashToast(): [ToastMessage | null, () => void] {
    const { flash } = usePage().props;
    const [toast, setToast] = useState<ToastMessage | null>(null);
    const counter = useRef(0);

    useEffect(() => {
        if (flash?.success) {
            counter.current += 1;
            setToast({ id: counter.current, text: flash.success });
        }
    }, [flash]);

    return [toast, () => setToast(null)];
}
