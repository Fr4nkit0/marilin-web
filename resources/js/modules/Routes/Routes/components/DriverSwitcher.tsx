import React from 'react';
import { Link } from '@inertiajs/react';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Check, ChevronDown } from 'lucide-react';

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

/**
 * The driver's name as the page heading, opening a menu of drivers to jump to another one without going back.
 * Each driver is a link, so it can also open in a new tab.
 */
export default function DriverSwitcher({ drivers, currentId, date }: Props) {
    const current = drivers.find((driver) => driver.id === currentId);

    return (
        <Menu>
            <h1 className="text-2xl font-semibold text-ink tracking-tight">
                <MenuButton
                    aria-label={`${current?.name ?? ''}, cambiar de repartidor`}
                    className="group -ml-2 inline-flex font-semibold min-h-11 sm:min-h-0 items-center gap-1.5 rounded-lg px-2 py-0.5 text-left hover:bg-surface-1 data-[open]:bg-surface-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 transition-colors cursor-pointer"
                >
                    {current?.name}
                    <ChevronDown
                        aria-hidden="true"
                        className="w-5 h-5 shrink-0 text-ink-muted group-hover:text-ink transition-transform group-data-[open]:rotate-180"
                    />
                </MenuButton>
            </h1>
            <MenuItems
                anchor={{ to: 'bottom start', gap: 6 }}
                className="z-50 w-64 max-h-80 overflow-y-auto rounded-xl border border-hairline bg-canvas p-1 shadow-lg focus:outline-none"
            >
                {drivers.map((driver) => {
                    const isCurrent = driver.id === currentId;

                    return (
                        <MenuItem key={driver.id}>
                            <Link
                                href={route('drivers.show', { repartidor: driver.id, date })}
                                aria-current={isCurrent ? 'page' : undefined}
                                className={`flex items-center justify-between gap-2 rounded-lg px-3 py-2 min-h-11 sm:min-h-0 text-sm data-[focus]:bg-surface-1 ${
                                    isCurrent ? 'font-semibold text-sky-700 dark:text-sky-300' : 'text-ink-secondary'
                                }`}
                            >
                                {driver.name}
                                {isCurrent && <Check aria-hidden="true" className="w-4 h-4 shrink-0" />}
                            </Link>
                        </MenuItem>
                    );
                })}
            </MenuItems>
        </Menu>
    );
}
