import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { CheckCheck } from 'lucide-react';
import { PaginatedData } from '@/types';
import { ReviewFilters, ReviewItem, ReviewType } from './types';
import { date, dateTime } from '@/lib/format';
import { useListFilters } from '@/lib/useListFilters';
import Pagination from '@/ui/Pagination';

interface Props {
    items: PaginatedData<ReviewItem>;
    filters: ReviewFilters;
    drivers: { id: number; name: string }[];
}

const TYPE_LABELS: Record<ReviewType, string> = {
    visit: 'Visita',
    sale: 'Venta',
    payment: 'Cobro',
    route_stop: 'Parada reprogramada',
    customer: 'Cliente',
    container_movement: 'Movimiento de envases',
    customer_agreement: 'Acuerdo',
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 font-medium';

function formatDate(item: ReviewItem): string {
    // A rescheduled stop belongs to a day, not to a moment of it.
    return item.type === 'route_stop' ? date(item.occurred_at) : dateTime(item.occurred_at);
}

export default function Index({ items, filters, drivers }: Props) {
    const filter = useListFilters(
        'review.index',
        {
            driver_id: filters.driver_id ? String(filters.driver_id) : '',
            from: filters.from ?? '',
            to: filters.to ?? '',
            type: filters.type ?? '',
            status: filters.status ?? 'pending',
        },
        { driver_id: '', from: '', to: '', type: '', status: 'pending' },
    );

    const markReviewed = (item: ReviewItem) => {
        router.patch(route('review.reviewed', { type: item.type, id: item.id }), {}, { preserveScroll: true });
    };

    return (
        <AdminLayout
            header={<span className="text-sm font-medium text-ink-muted">Revisión</span>}
        >
            <Head title="Revisión" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-ink tracking-tight">Revisión</h1>
                <p className="text-sm font-medium text-ink-muted mt-1 tracking-wide">
                    {items.total} {items.total === 1 ? 'registro' : 'registros'} con los filtros aplicados.
                </p>
            </div>

            {/* Filters */}
            <div className="bg-canvas/80 backdrop-blur-md rounded-2xl p-4 mb-6 shadow-sm border border-hairline/60 ">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-12 items-end">
                    <label className="sm:col-span-3 text-xs font-medium text-ink-muted">
                        Repartidor
                        <select
                            className={`${inputClass} mt-1`}
                            value={filter.values.driver_id}
                            onChange={(e) => filter.set('driver_id', e.target.value)}
                        >
                            <option value="">Todos los repartidores</option>
                            {drivers.map((driver) => (
                                <option key={driver.id} value={driver.id}>
                                    {driver.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="sm:col-span-2 text-xs font-medium text-ink-muted">
                        Desde
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={filter.values.from}
                            onChange={(e) => filter.set('from', e.target.value)}
                        />
                    </label>

                    <label className="sm:col-span-2 text-xs font-medium text-ink-muted">
                        Hasta
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={filter.values.to}
                            onChange={(e) => filter.set('to', e.target.value)}
                        />
                    </label>

                    <label className="sm:col-span-3 text-xs font-medium text-ink-muted">
                        Tipo
                        <select
                            className={`${inputClass} mt-1`}
                            value={filter.values.type}
                            onChange={(e) => filter.set('type', e.target.value)}
                        >
                            <option value="">Todos los tipos</option>
                            {(Object.keys(TYPE_LABELS) as ReviewType[]).map((type) => (
                                <option key={type} value={type}>
                                    {TYPE_LABELS[type]}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="sm:col-span-2 text-xs font-medium text-ink-muted">
                        Estado
                        <select
                            className={`${inputClass} mt-1`}
                            value={filter.values.status}
                            onChange={(e) => filter.set('status', e.target.value)}
                        >
                            <option value="pending">Pendiente de revisión</option>
                            <option value="reviewed">Revisado</option>
                            <option value="all">Todos</option>
                        </select>
                    </label>
                </div>
            </div>

            {/* List */}
            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden ">
                <div className="table-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-canvas/80 backdrop-blur-md border-b border-hairline text-ink-muted">
                            <tr>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Tipo</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Repartidor</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Cliente</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Fecha</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Motivo</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide text-right">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-10 text-center text-ink-muted text-sm">
                                        <span className="table-empty">No hay registros con los filtros aplicados.</span>
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={`${item.type}-${item.id}`} className="hover:bg-canvas-soft transition-colors">
                                        <td className="px-6 py-4 font-medium text-ink whitespace-nowrap">
                                            {TYPE_LABELS[item.type]}
                                        </td>
                                        <td className="px-6 py-4 text-ink-secondary">
                                            {item.driver ?? <span className="text-ink-subtle italic">Sin asignar</span>}
                                        </td>
                                        <td className="px-6 py-4 text-ink-secondary">{item.customer}</td>
                                        <td className="px-6 py-4 text-ink-secondary whitespace-nowrap">{formatDate(item)}</td>
                                        <td className="px-6 py-4 text-ink-secondary max-w-sm">
                                            {item.reasons.length === 1 ? (
                                                item.reasons[0]
                                            ) : (
                                                <ul className="list-disc pl-4 space-y-1">
                                                    {item.reasons.map((reason) => (
                                                        <li key={reason}>{reason}</li>
                                                    ))}
                                                </ul>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right whitespace-nowrap">
                                            {item.reviewed_at ? (
                                                <StatusBadge
                                                    status="finalizado"
                                                    label={`Revisado${item.reviewed_by ? ` por ${item.reviewed_by}` : ''}`}
                                                />
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => markReviewed(item)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-sky-600 text-white hover:bg-sky-700 font-medium transition text-xs shadow-sm cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                >
                                                    <CheckCheck className="w-3.5 h-3.5" />
                                                    Marcar como revisado
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination page={items} />
            </div>
        </AdminLayout>
    );
}
