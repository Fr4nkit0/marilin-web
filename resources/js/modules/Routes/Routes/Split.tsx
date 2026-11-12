import React, { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import { buttonClass } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';
import type { DriverOption } from './components/MoveStopsBar';
import SplitGroupCard from './components/SplitGroupCard';
import type { SplitGroup, SplitRoute } from './types';

interface Props {
    route: SplitRoute;
    groups: SplitGroup[];
    drivers: DriverOption[];
}

/** The move's error, whichever field it came under. */
function moveError(errors: Record<string, string | undefined>): string | undefined {
    return errors.move ?? errors.driver_id ?? errors.stop_ids ?? Object.entries(errors).find(([key]) => key.startsWith('stop_ids.'))?.[1];
}

/**
 * "Repartir ruta": the absent driver's stops still to be made, by neighborhood, next to who already delivers there
 * that day. Each assignment moves the stops and comes back here without them, until the route is split.
 */
export default function Split({ route: splitRoute, groups, drivers }: Props) {
    const errors = usePage().props.errors as Record<string, string | undefined>;
    const [lastAssigned, setLastAssigned] = useState<string | null>(null);
    const error = moveError(errors);
    const routesHref = route('routes.index', { date: splitRoute.rawDate });
    const stopsLeft = groups.reduce((total, group) => total + group.stops.length, 0);

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Despacho y rutas</span>}>
            <Head title={`Repartir ruta de ${splitRoute.driverName}`} />

            <div className="mb-4">
                <Link href={routesHref} className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink transition-colors">
                    <ArrowLeft className="w-4 h-4" />
                    Volver a rutas
                </Link>
            </div>

            <PageHeader
                title={`Repartir ruta de ${splitRoute.driverName} — ${splitRoute.date}`}
                description={
                    stopsLeft === 0
                        ? undefined
                        : `${stopsLeft === 1 ? 'Queda 1 parada' : `Quedan ${stopsLeft} paradas`} por repartir. Cada barrio sugiere a quien ya reparte ahí ese día; el cliente sigue siendo de su repartidor.`
                }
            />

            {groups.length === 0 ? (
                <div className="bg-canvas border border-hairline/60 rounded-2xl p-12 text-center shadow-sm">
                    <CheckCircle2 className="w-12 h-12 text-ink-subtle mx-auto mb-4" aria-hidden="true" />
                    <h3 className="text-lg font-semibold text-ink">No quedan paradas para repartir</h3>
                    <p className="text-sm text-ink-muted mt-2">Todas las paradas pendientes de esta ruta ya tienen repartidor.</p>
                    <Link href={routesHref} className={`${buttonClass('secondary')} mt-6`}>
                        Volver a rutas
                    </Link>
                </div>
            ) : (
                <div className="space-y-4">
                    {groups.map((group) => (
                        <SplitGroupCard
                            key={group.name}
                            routeId={splitRoute.id}
                            group={group}
                            drivers={drivers}
                            error={lastAssigned === group.name ? error : undefined}
                            onAssign={() => setLastAssigned(group.name)}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
