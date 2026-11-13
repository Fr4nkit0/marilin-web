import React, { useRef, useState } from 'react';
import { ArrowRightLeft, MapPin } from 'lucide-react';
import { statusOrder, stopResultLabel, stopStatusLabel } from '@/lib/format';
import { useSort } from '@/lib/sort';
import { StatusBadge } from '@/ui/Badge';
import { Checkbox } from '@/ui/Field';
import LocalPagination, { pageOf } from '@/ui/LocalPagination';
import SortableTh from '@/ui/SortableTh';
import SortBar from '@/ui/SortBar';
import RequestedVisitMark from './RequestedVisitMark';
import type { RouteStopRow } from '../types';

const STOPS_PER_PAGE = 15;

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

    // Paged after sorting, so a sort orders the whole route, not just the page on screen.
    const [page, setPage] = useState(1);
    const card = useRef<HTMLDivElement>(null);
    // A new page starts at the top of the list, not where the pager left the screen.
    const showPage = (next: number) => {
        setPage(next);
        card.current?.scrollIntoView({ block: 'start' });
    };
    const stopsPage = pageOf(table.rows, page, STOPS_PER_PAGE);

    type SortKey = Parameters<typeof table.toggle>[0];
    // On a phone the headers are hidden (the rows are cards), so only the select-all one stays, as a bar.
    const header = (key: SortKey, label: string, className = 'px-4 py-3') => (
        <SortableTh direction={table.directionOf(key)} onSort={() => table.toggle(key)} className={`${className} max-sm:!hidden`}>
            {label}
        </SortableTh>
    );

    const sortOptions = (
        [
            ['number', 'Parada'],
            ['customer', 'Cliente'],
            ['address', 'Dirección'],
            ['status', 'Estado'],
            ['arrivedAt', 'Hora'],
        ] as [SortKey, string][]
    ).map(([key, label]) => ({ label, direction: table.directionOf(key), onSort: () => table.toggle(key) }));

    return (
        <div>
            <h4 className="text-sm font-semibold text-ink mb-4 flex items-center gap-2">
                <MapPin className="w-4 h-4 text-ink-muted" />
                Hoja de Ruta y Paradas ({stops.length})
            </h4>

            <div ref={card} className="bg-canvas border border-hairline/60 rounded-xl shadow-sm overflow-hidden">
                <div className="table-scroll">
                    <SortBar options={sortOptions} />
                    <table className="table-cards w-full text-left text-sm">
                        <thead
                            className={`bg-canvas-soft/80 backdrop-blur-md text-ink-muted font-medium text-xs border-b border-hairline/60 ${
                                selectable ? 'max-sm:!block' : ''
                            }`}
                        >
                            <tr className="max-sm:!py-2">
                                {selectable && (
                                    <th scope="col" className="pl-4 py-3 w-8 max-sm:w-auto max-sm:!justify-start max-sm:min-h-11">
                                        {/* A label so on a phone its text is part of the tap target. */}
                                        <label className="inline-flex items-center gap-2 cursor-pointer max-sm:min-h-11">
                                            <Checkbox
                                                aria-label="Seleccionar todas las paradas pendientes"
                                                checked={allSelected}
                                                disabled={movableIds.length === 0}
                                                onChange={() => onSelect(allSelected ? [] : movableIds)}
                                            />
                                            <span aria-hidden="true" className="sm:hidden text-xs font-medium text-ink-secondary">
                                                Todas las pendientes
                                            </span>
                                        </label>
                                    </th>
                                )}
                                {header('number', '#', 'px-4 py-3 w-16')}
                                {header('customer', 'Cliente')}
                                {header('address', 'Dirección')}
                                {header('status', 'Estado')}
                                {header('arrivedAt', 'Hora')}
                                <th scope="col" className="px-4 py-3 max-sm:!hidden">
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
                                stopsPage.rows.map(({ stop, number }) => (
                                    <tr
                                        key={stop.id}
                                        className="border-b border-hairline last:border-0 hover:bg-canvas-soft/50 transition-colors"
                                    >
                                        {selectable && (
                                            <td data-label="Mover" className={`pl-4 py-3 ${isMovable(stop) ? '' : 'max-sm:!hidden'}`}>
                                                {isMovable(stop) && (
                                                    <Checkbox
                                                        aria-label={`Seleccionar ${stop.customer.name}`}
                                                        checked={selected.includes(stop.id)}
                                                        onChange={() => toggle(stop.id)}
                                                    />
                                                )}
                                            </td>
                                        )}
                                        <td data-label="Parada" className="px-4 py-3">
                                            <span className="inline-flex w-7 h-7 rounded-full bg-surface-1 text-ink-secondary items-center justify-center text-xs font-medium tabular-nums">
                                                {number}
                                            </span>
                                        </td>
                                        <td data-primary className="px-4 py-3 max-sm:order-first">
                                            <span className="font-medium text-ink block">{stop.customer.name}</span>
                                            {stop.customer.phone && (
                                                <span className="text-xs font-normal text-ink-muted mt-0.5 block">{stop.customer.phone}</span>
                                            )}
                                            {stop.is_requested && <RequestedVisitMark note={stop.note} />}
                                            {stop.movedFrom && (
                                                <span className="mt-1 inline-flex items-center gap-1 rounded-full border border-sky-200/60 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:border-sky-500/30 dark:bg-sky-500/15 dark:text-sky-300">
                                                    <ArrowRightLeft className="h-3 w-3" aria-hidden="true" />
                                                    Movida de {stop.movedFrom}
                                                </span>
                                            )}
                                        </td>
                                        <td data-label="Dirección" className="px-4 py-3 text-ink-muted">{stop.customer.address}</td>
                                        <td data-label="Estado" className="px-4 py-3">
                                            <StatusBadge status={stop.status} label={stopStatusLabel(stop.status)} />
                                        </td>
                                        <td data-label="Hora" className="px-4 py-3 text-ink-muted tabular-nums whitespace-nowrap">
                                            {stop.arrivedAt ? `${stop.arrivedAt} hs` : '—'}
                                        </td>
                                        <td data-label="Resultado" className="px-4 py-3 text-ink-muted">
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
                <LocalPagination
                    page={stopsPage.page}
                    lastPage={stopsPage.lastPage}
                    total={stops.length}
                    noun="paradas"
                    onPage={showPage}
                />
            </div>
        </div>
    );
}
