import React from 'react';
import { ArrowRightLeft, MapPin } from 'lucide-react';
import { statusOrder, stopResultLabel, stopStatusLabel } from '@/lib/format';
import { useSort } from '@/lib/sort';
import { StatusBadge } from '@/ui/Badge';
import { Checkbox } from '@/ui/Field';
import SortableTh from '@/ui/SortableTh';
import RequestedVisitMark from './RequestedVisitMark';
import type { RouteStopRow } from '../types';

export interface RouteStop extends RouteStopRow {
    plannedSequence: number;
    outcome?: string;
    /** "Cobertura": the absent driver whose route the stop was moved from. */
    movedFrom: string | null;
}

interface Props {
    stops: RouteStop[];
    /** The stops picked to move to another driver; only with onSelect. */
    selected?: number[];
    /** Given, the stops still to be made get a checkbox, so the admin can move them. */
    onSelect?: (ids: number[]) => void;
}

/** A stop that can still go to another driver: one not made yet nor rescheduled. */
function isMovable(stop: RouteStop): boolean {
    return !stop.done && stop.status !== 'RESCHEDULED';
}

/** A route's stops in the order the driver visits them; sorting by another column keeps each stop's number. */
export default function RouteStopsTable({ stops, selected = [], onSelect }: Props) {
    const selectable = onSelect !== undefined;
    const movableIds = stops.filter(isMovable).map((stop) => stop.id);
    const allSelected = movableIds.length > 0 && movableIds.every((id) => selected.includes(id));

    // Kept in route order, whatever order the admin clicked them in.
    const toggle = (id: number) =>
        onSelect?.(movableIds.filter((movable) => (movable === id ? !selected.includes(id) : selected.includes(movable))));

    // The number is the stop's place in the route, so it travels with the stop when the table is sorted.
    const numbered = stops.map((stop, index) => ({ stop, number: index + 1 }));
    const table = useSort(numbered, {
        number: (row) => row.number,
        customer: (row) => row.stop.customer.name,
        address: (row) => row.stop.customer.address,
        status: (row) => statusOrder(row.stop.status),
        arrivedAt: (row) => row.stop.arrivedAt,
    });

    const header = (key: Parameters<typeof table.toggle>[0], label: string, className = 'px-4 py-3') => (
        <SortableTh direction={table.directionOf(key)} onSort={() => table.toggle(key)} className={className}>
            {label}
        </SortableTh>
    );

    return (
        <div>
            <h4 className="text-sm font-semibold text-ink mb-4 flex items-center gap-2">
                <MapPin className="w-4 h-4 text-ink-muted" />
                Hoja de Ruta y Paradas ({stops.length})
            </h4>

            <div className="bg-canvas border border-hairline/60 rounded-xl shadow-sm table-scroll">
                <table className="w-full text-left text-sm">
                    <thead className="bg-canvas-soft/80 backdrop-blur-md text-ink-muted font-medium text-xs border-b border-hairline/60">
                        <tr>
                            {selectable && (
                                <th scope="col" className="pl-4 py-3 w-8">
                                    <Checkbox
                                        aria-label="Seleccionar todas las paradas pendientes"
                                        checked={allSelected}
                                        disabled={movableIds.length === 0}
                                        onChange={() => onSelect(allSelected ? [] : movableIds)}
                                    />
                                </th>
                            )}
                            {header('number', '#', 'px-4 py-3 w-16')}
                            {header('customer', 'Cliente')}
                            {header('address', 'Dirección')}
                            {header('status', 'Estado')}
                            {header('arrivedAt', 'Hora')}
                            <th scope="col" className="px-4 py-3">
                                Resultado
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {stops.length === 0 ? (
                            <tr>
                                <td colSpan={selectable ? 7 : 6} className="px-6 py-8 text-center text-ink-muted">
                                    <span className="table-empty">No hay paradas registradas en esta ruta.</span>
                                </td>
                            </tr>
                        ) : (
                            table.rows.map(({ stop, number }) => (
                                <tr
                                    key={stop.id}
                                    className="border-b border-hairline last:border-0 hover:bg-canvas-soft/50 transition-colors"
                                >
                                    {selectable && (
                                        <td className="pl-4 py-3">
                                            {isMovable(stop) && (
                                                <Checkbox
                                                    aria-label={`Seleccionar ${stop.customer.name}`}
                                                    checked={selected.includes(stop.id)}
                                                    onChange={() => toggle(stop.id)}
                                                />
                                            )}
                                        </td>
                                    )}
                                    <td className="px-4 py-3">
                                        <span className="inline-flex w-7 h-7 rounded-full bg-surface-1 text-ink-secondary items-center justify-center text-xs font-medium tabular-nums">
                                            {number}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="font-medium text-ink block">{stop.customer.name}</span>
                                        {stop.customer.phone && (
                                            <span className="text-xs text-ink-muted mt-0.5 block">{stop.customer.phone}</span>
                                        )}
                                        {stop.is_requested && <RequestedVisitMark note={stop.note} />}
                                        {stop.movedFrom && (
                                            <span className="mt-1 inline-flex items-center gap-1 rounded-full border border-sky-200/60 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/15 dark:text-sky-300">
                                                <ArrowRightLeft className="h-3 w-3" aria-hidden="true" />
                                                Movida de {stop.movedFrom}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-ink-muted">{stop.customer.address}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={stop.status} label={stopStatusLabel(stop.status)} />
                                    </td>
                                    <td className="px-4 py-3 text-ink-muted tabular-nums whitespace-nowrap">
                                        {stop.arrivedAt ? `${stop.arrivedAt} hs` : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-ink-muted">
                                        {stop.outcome ? (
                                            <span className="font-medium text-ink text-sm">{stop.outcome}</span>
                                        ) : (
                                            <span className={stop.done ? 'text-ink-subtle italic' : 'text-ink-subtle'}>
                                                {stopResultLabel(stop)}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
