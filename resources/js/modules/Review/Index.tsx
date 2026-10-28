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
    payment: 'Pago',
    route_stop: 'Parada reprogramada',
    customer: 'Cliente',
    container_movement: 'Movimiento de envases',
    customer_agreement: 'Acuerdo',
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20 focus:border-slate-900 font-medium';

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
            header={<span className="text-sm font-medium text-slate-500">Revisión</span>}
        >
            <Head title="Revisión - Marilin" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">Bandeja de revisión</h1>
                <p className="text-sm font-medium text-slate-500 mt-1 tracking-wide">
                    {items.total} {items.total === 1 ? 'registro' : 'registros'} con los filtros aplicados.
                </p>
            </div>

            {/* Filters */}
            <div className="bg-white/80 backdrop-blur-md rounded-2xl p-4 mb-6 shadow-sm border border-gray-200/60">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-12 items-end">
                    <label className="sm:col-span-3 text-xs font-medium text-slate-500">
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

                    <label className="sm:col-span-2 text-xs font-medium text-slate-500">
                        Desde
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={filter.values.from}
                            onChange={(e) => filter.set('from', e.target.value)}
                        />
                    </label>

                    <label className="sm:col-span-2 text-xs font-medium text-slate-500">
                        Hasta
                        <input
                            type="date"
                            className={`${inputClass} mt-1`}
                            value={filter.values.to}
                            onChange={(e) => filter.set('to', e.target.value)}
                        />
                    </label>

                    <label className="sm:col-span-3 text-xs font-medium text-slate-500">
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

                    <label className="sm:col-span-2 text-xs font-medium text-slate-500">
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
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-white/80 backdrop-blur-md border-b border-gray-100 text-slate-500">
                            <tr>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Tipo</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Repartidor</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Cliente</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Fecha</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Motivo</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide text-right">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-10 text-center text-slate-500 text-sm">
                                        No hay registros con los filtros aplicados.
                                    </td>
                                </tr>
                            ) : (
                                items.data.map((item) => (
                                    <tr key={`${item.type}-${item.id}`} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">
                                            {TYPE_LABELS[item.type]}
                                        </td>
                                        <td className="px-6 py-4 text-slate-700">
                                            {item.driver ?? <span className="text-gray-400 italic">Sin asignar</span>}
                                        </td>
                                        <td className="px-6 py-4 text-slate-700">{item.customer}</td>
                                        <td className="px-6 py-4 text-slate-700 whitespace-nowrap">{formatDate(item)}</td>
                                        <td className="px-6 py-4 text-slate-600 max-w-sm">{item.reason}</td>
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
                                                    className="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-slate-900 text-white hover:bg-slate-800 font-medium transition text-xs shadow-sm cursor-pointer"
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
