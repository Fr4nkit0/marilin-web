import { Link } from '@inertiajs/react';

interface SettingsTab {
    label: string;
    /** The tab's index route; its section is that name without the `.index` suffix. */
    routeName: string;
}

const TABS: SettingsTab[] = [
    { label: 'Barrios', routeName: 'neighborhoods.index' },
    { label: 'Resultados de visita', routeName: 'visit-outcomes.index' },
    { label: 'Tipos de envase', routeName: 'container-types.index' },
    { label: 'Medios de pago', routeName: 'payment-methods.index' },
];

const section = (routeName: string): string => routeName.replace(/\.index$/, '.*');

/** The settings section's tabs, one per configuration catalog; a catalog whose page does not exist yet is left out. */
export default function SettingsTabs() {
    const tabs = TABS.filter((tab) => route().has(tab.routeName));

    return (
        <nav aria-label="Configuración" className="mb-6 flex gap-1 overflow-x-auto border-b border-hairline">
            {tabs.map((tab) => {
                const active = route().current(section(tab.routeName));

                return (
                    <Link
                        key={tab.routeName}
                        href={route(tab.routeName)}
                        aria-current={active ? 'page' : undefined}
                        className={`-mb-px whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                            active
                                ? 'border-sky-600 text-sky-700 dark:border-sky-400 dark:text-sky-300'
                                : 'border-transparent text-ink-muted hover:text-ink hover:border-hairline-strong'
                        }`}
                    >
                        {tab.label}
                    </Link>
                );
            })}
        </nav>
    );
}
