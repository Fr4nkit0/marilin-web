import React, { useId, useState } from 'react';
import { router } from '@inertiajs/react';
import { ChevronDown, MapPin, Users } from 'lucide-react';
import Button from '@/ui/Button';
import DataTable, { type Column } from '@/ui/DataTable';
import { Checkbox, FieldError, Label, Select } from '@/ui/Field';
import type { SplitGroup, SplitStop } from '../types';
import type { DriverOption } from './MoveStopsBar';
import { assignLabel, preselectedDriverId, stopsToAssign, suggestionText, toggleStop } from './splitGroup';

interface Props {
    routeId: number;
    group: SplitGroup;
    /** Every enabled driver but the route's. */
    drivers: DriverOption[];
    /** The move's error, shown only on the neighborhood that was assigned last. */
    error?: string;
    onAssign: () => void;
}

/**
 * One neighborhood of the route to split: who already delivers there that day, and a driver to give it to.
 * It assigns the whole neighborhood, or only the stops chosen in its list, through the stops move.
 */
export default function SplitGroupCard({ routeId, group, drivers, error, onAssign }: Props) {
    const [driverId, setDriverId] = useState(() => preselectedDriverId(group, drivers));
    const [selected, setSelected] = useState<number[]>([]);
    const [expanded, setExpanded] = useState(false);
    const [processing, setProcessing] = useState(false);
    // useId: a neighborhood name may hold spaces, which would break id references.
    const baseId = useId();
    const selectId = `${baseId}-driver`;
    const listId = `${baseId}-stops`;
    const others = group.suggestions.slice(1);

    const assign = () => {
        onAssign();
        router.post(
            route('routes.stops.move', routeId),
            { stop_ids: stopsToAssign(group, selected), driver_id: driverId === '' ? null : Number(driverId) },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setSelected([]),
            },
        );
    };

    const allSelected = group.stops.every((stop) => selected.includes(stop.id));

    const columns: Column<SplitStop>[] = [
        {
            header: (
                <Checkbox
                    aria-label={`Seleccionar todas las paradas de ${group.name}`}
                    checked={allSelected}
                    onChange={() => setSelected(allSelected ? [] : group.stops.map((stop) => stop.id))}
                />
            ),
            // On a phone card the box reads as its own line, since the header naming it is hidden there.
            label: 'Elegir',
            className: 'w-10',
            cell: (stop) => (
                <Checkbox
                    aria-label={`Seleccionar ${stop.customer}`}
                    checked={selected.includes(stop.id)}
                    onChange={() => setSelected(toggleStop(selected, stop.id))}
                />
            ),
        },
        { header: 'Cliente', cell: (stop) => <span className="font-medium text-ink">{stop.customer}</span> },
        { header: 'Dirección', cell: (stop) => <span className="text-ink-secondary">{stop.street ?? 'Sin dirección'}</span> },
        {
            header: 'Hora',
            align: 'right',
            cell: (stop) => <span className="text-ink-muted">{stop.scheduledTime ? `${stop.scheduledTime} hs` : '—'}</span>,
        },
    ];

    return (
        <section aria-labelledby={`${listId}-title`} className="bg-canvas border border-hairline/60 rounded-2xl shadow-sm overflow-hidden">
            <div className="p-4 sm:p-5 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h3 id={`${listId}-title`} className="text-base font-semibold text-ink flex items-center gap-2">
                            <MapPin className="w-4 h-4 text-ink-muted" aria-hidden="true" />
                            {group.name}
                        </h3>
                        <span className="text-sm text-ink-muted tabular-nums">
                            {group.stops.length === 1 ? '1 parada' : `${group.stops.length} paradas`}
                        </span>
                    </div>
                    <p className={`mt-1.5 text-sm flex items-center gap-1.5 ${group.suggestions.length > 0 ? 'text-ink-secondary' : 'text-ink-muted'}`}>
                        <Users className="w-4 h-4 text-ink-subtle shrink-0" aria-hidden="true" />
                        {suggestionText(group)}
                    </p>
                    {others.length > 0 && (
                        <div className="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-ink-muted">
                            <span>También:</span>
                            {others.map((other) => (
                                <span
                                    key={other.driverId}
                                    className="inline-flex items-center rounded-full border border-hairline/60 bg-surface-1 px-2.5 py-0.5 font-medium text-ink-secondary tabular-nums"
                                >
                                    {other.driverName} ({other.stops})
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <div className="w-56">
                        <Label htmlFor={selectId} className="mb-1.5 text-xs">
                            Pasa a
                        </Label>
                        <Select id={selectId} value={driverId} onChange={(e) => setDriverId(e.target.value)} invalid={Boolean(error)}>
                            <option value="">Elegí un repartidor</option>
                            {drivers.map((driver) => (
                                <option key={driver.id} value={driver.id}>
                                    {driver.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <Button onClick={assign} processing={processing} disabled={driverId === ''}>
                        {assignLabel(group, selected)}
                    </Button>
                </div>
            </div>

            {error && <FieldError message={error} className="px-4 sm:px-5 pb-3" />}

            <div className="border-t border-hairline">
                <button
                    type="button"
                    onClick={() => setExpanded(!expanded)}
                    aria-expanded={expanded}
                    aria-controls={listId}
                    className="w-full flex items-center justify-between px-4 sm:px-5 py-2.5 text-sm font-medium text-ink-secondary hover:text-ink hover:bg-canvas-soft/50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sky-600"
                >
                    <span>
                        {expanded ? 'Ocultar paradas' : 'Elegir paradas'}
                        {selected.length > 0 && <span className="ml-2 text-ink-muted tabular-nums">({selected.length} elegidas)</span>}
                    </span>
                    <ChevronDown className={`w-4 h-4 transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`} aria-hidden="true" />
                </button>
                {expanded && (
                    <div id={listId} className="border-t border-hairline">
                        {/* The header's select-all box is hidden on a phone (cards); this one takes its place there. */}
                        <label className="sm:hidden flex min-h-11 items-center gap-3 px-4 border-b border-hairline text-sm font-medium text-ink-secondary cursor-pointer">
                            <Checkbox
                                checked={allSelected}
                                onChange={() => setSelected(allSelected ? [] : group.stops.map((stop) => stop.id))}
                            />
                            Seleccionar todas
                        </label>
                        <DataTable columns={columns} rows={group.stops} rowKey={(stop) => stop.id} empty="No quedan paradas en este barrio." />
                    </div>
                )}
            </div>
        </section>
    );
}
