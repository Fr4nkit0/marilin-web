/** Whether a settings catalog row is offered by the app. */
export default function EnabledBadge({ enabled }: { enabled: boolean }) {
    return (
        <span
            className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                enabled ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-surface-1 text-ink-muted'
            }`}
        >
            {enabled ? 'Habilitado' : 'Deshabilitado'}
        </span>
    );
}
