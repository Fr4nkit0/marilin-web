const PICKER_TYPES = new Set(['date', 'time', 'datetime-local', 'month', 'week']);

/**
 * Opens the native picker when a date or time field is tapped anywhere on it, not only on its small calendar
 * icon: mobile browsers and touch emulation often ignore the icon. One listener on the document covers every
 * field, including those rendered later. Returns a function that removes the listener.
 */
export function openPickerOnTap(root: Document): () => void {
    const onClick = (event: Event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement) || !PICKER_TYPES.has(field.type)) return;
        if (field.disabled || field.readOnly || typeof field.showPicker !== 'function') return;

        try {
            field.showPicker();
        } catch {
            // The browser may refuse (no user activation, cross-origin frame); the field still works by typing.
        }
    };

    root.addEventListener('click', onClick);

    return () => root.removeEventListener('click', onClick);
}
