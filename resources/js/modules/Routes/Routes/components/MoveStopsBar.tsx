import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
import Button from '@/ui/Button';
import { FieldError, Select } from '@/ui/Field';

export interface DriverOption {
    id: number;
    name: string;
}

interface Props {
    routeId: number;
    /** The enabled drivers that can take the stops: every one but the route's. */
    drivers: DriverOption[];
    selected: number[];
    /** The stops moved: the selection is over. */
    onMoved: () => void;
}

/**
 * "Cobertura": the admin sends the selected stops of a route not dispatched yet to another driver's route of the same day,
 * even one already on the street.
 * Repeated, it splits an absent driver's route among several drivers.
 */
export default function MoveStopsBar({ routeId, drivers, selected, onMoved }: Props) {
    const [driverId, setDriverId] = useState('');
    const [processing, setProcessing] = useState(false);
    const errors = usePage().props.errors as Record<string, string | undefined>;
    const error = errors.move ?? errors.driver_id ?? errors.stop_ids;

    const move = () => {
        router.post(
            route('routes.stops.move', routeId),
            { stop_ids: selected, driver_id: driverId === '' ? null : Number(driverId) },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setDriverId('');
                    onMoved();
                },
            },
        );
    };

    return (
        <div className="mb-4 rounded-xl border border-hairline/60 bg-canvas p-4 shadow-sm">
            <div className="flex flex-wrap items-center gap-3">
                <ArrowRightLeft className="h-4 w-4 text-ink-muted" aria-hidden="true" />
                <span className="text-sm text-ink-secondary">
                    {selected.length === 0
                        ? 'Elegí las paradas que cubre otro repartidor.'
                        : `${selected.length} ${selected.length === 1 ? 'parada seleccionada' : 'paradas seleccionadas'}`}
                </span>
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    <div className="w-56">
                        <Select
                            aria-label="Repartidor que cubre"
                            value={driverId}
                            onChange={(e) => setDriverId(e.target.value)}
                            invalid={Boolean(errors.driver_id)}
                        >
                            <option value="">Mover a…</option>
                            {drivers.map((driver) => (
                                <option key={driver.id} value={driver.id}>
                                    {driver.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <Button onClick={move} processing={processing} disabled={selected.length === 0 || driverId === ''}>
                        Mover paradas
                    </Button>
                </div>
            </div>
            {error && <FieldError message={error} className="mt-2" />}
            <p className="mt-2 text-xs text-ink-muted">
                Van a la ruta del día del repartidor elegido; el cliente sigue siendo de su repartidor.
            </p>
        </div>
    );
}
