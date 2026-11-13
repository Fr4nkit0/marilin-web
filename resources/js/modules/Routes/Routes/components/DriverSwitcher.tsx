import React from 'react';
import { router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

export interface DriverOption {
    id: number;
    name: string;
}

interface Props {
    drivers: DriverOption[];
    currentId: number;
    /** The day on screen, kept when switching so the admin compares drivers on the same day. */
    date: string;
}

/** The driver's name as a heading that opens the list of drivers, to jump to another one without going back. */
export default function DriverSwitcher({ drivers, currentId, date }: Props) {
    return (
        <h1 className="relative inline-flex items-center">
            <select
                aria-label="Cambiar de repartidor"
                value={currentId}
                onChange={(e) => router.get(route('drivers.show', Number(e.target.value)), { date })}
                className="appearance-none cursor-pointer rounded-lg border-0 bg-transparent py-0.5 pl-1 -ml-1 pr-8 text-2xl font-semibold tracking-tight text-ink hover:bg-surface-1 focus:outline-none focus:ring-2 focus:ring-sky-600 transition-colors"
            >
                {drivers.map((driver) => (
                    <option key={driver.id} value={driver.id} className="text-base font-normal bg-canvas text-ink">
                        {driver.name}
                    </option>
                ))}
            </select>
            <ChevronDown aria-hidden="true" className="pointer-events-none absolute right-1.5 w-5 h-5 text-ink-muted" />
        </h1>
    );
}
