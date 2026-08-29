import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, KpiCard, PaymentMethodBadge, StatusBadge } from '@/Components/StitchUi';
import {
    Search,
    CircleDollarSign,
    DollarSign,
    CheckCircle2,
    Calendar,
    Filter,
    User,
    ArrowUpRight,
} from 'lucide-react';

interface PaymentItem {
    id: number;
    amount: string | number;
    paid_at: string;
    status: string; // 'CONFIRMED', 'CANCELLED'
    customer?: {
        id: number;
        name: string;
    };
    payment_method?: {
        name: string;
        code: string;
    };
    recipient?: {
        name: string;
    };
    visit?: {
        repartidor?: {
            user?: {
                name: string;
            };
        };
    };
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    payments: PaginatedData<PaymentItem>;
    metrics: {
        totalCollected: number;
        cashTotal: number;
        transferTotal: number;
    };
    filters: {
        search?: string;
        date: string;
        payment_method_id?: string;
        status?: string;
    };
    paymentMethods: { id: number; name: string; code: string }[];
}

export default function PaymentsIndex({ payments, metrics, filters, paymentMethods }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [date, setDate] = useState(filters.date || '');
    const [paymentMethodId, setPaymentMethodId] = useState(filters.payment_method_id || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('payments.index'),
            {
                search: search || undefined,
                date: date || undefined,
                payment_method_id: paymentMethodId || undefined,
                status: status !== 'all' ? status : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleFilterChange = (key: string, value: string) => {
        const newFilters = {
            search,
            date: key === 'date' ? value : date,
            payment_method_id: key === 'payment_method_id' ? value : paymentMethodId,
            status: key === 'status' ? value : status,
        };

        if (key === 'date') setDate(value);
        if (key === 'payment_method_id') setPaymentMethodId(value);
        if (key === 'status') setStatus(value);

        router.get(
            route('payments.index'),
            {
                search: newFilters.search || undefined,
                date: newFilters.date || undefined,
                payment_method_id: newFilters.payment_method_id || undefined,
                status: newFilters.status !== 'all' ? newFilters.status : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Tesorería
                    </span>
                </div>
            }
        >
            <Head title="Conciliación de Cobros - Marilin" />

            <PageHeader
                title="Conciliación de Cobros"
                description={`Registro de pagos recibidos por choferes y transferencias bancarias para ${filters.date}.`}
            />

            {/* Metrics */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <KpiCard
                    title="Total Recaudado"
                    value={`$${metrics.totalCollected.toLocaleString('es-AR')}`}
                    icon={CircleDollarSign}
                    variant="success"
                    subtitle="Cobranza confirmada hoy"
                />
                <KpiCard
                    title="Efectivo en Mano"
                    value={`$${metrics.cashTotal.toLocaleString('es-AR')}`}
                    icon={DollarSign}
                    variant="primary"
                    subtitle="A rendir por los repartidores"
                />
                <KpiCard
                    title="Transferencias Bancarias"
                    value={`$${metrics.transferTotal.toLocaleString('es-AR')}`}
                    icon={CheckCircle2}
                    variant="accent"
                    subtitle="Acreditadas en cuenta"
                />
            </div>

            {/* Filters */}
            <div className="bg-white rounded-lg border border-slate-200 p-4 mb-6 shadow-2xs">
                <form onSubmit={handleSearch} className="grid grid-cols-1 gap-3 sm:grid-cols-12 items-center">
                    {/* Search */}
                    <div className="sm:col-span-4 relative">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por cliente..."
                            className="w-full pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    {/* Date */}
                    <div className="sm:col-span-3">
                        <input
                            type="date"
                            value={date}
                            onChange={(e) => handleFilterChange('date', e.target.value)}
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                        />
                    </div>

                    {/* Payment Method */}
                    <div className="sm:col-span-3">
                        <select
                            value={paymentMethodId}
                            onChange={(e) => handleFilterChange('payment_method_id', e.target.value)}
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                        >
                            <option value="">Todos los Métodos</option>
                            {paymentMethods.map((pm) => (
                                <option key={pm.id} value={pm.id}>
                                    {pm.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Status */}
                    <div className="sm:col-span-2">
                        <select
                            value={status}
                            onChange={(e) => handleFilterChange('status', e.target.value)}
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                        >
                            <option value="all">Todos los Estados</option>
                            <option value="CONFIRMED">Confirmados</option>
                            <option value="CANCELLED">Cancelados</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Payments Table */}
            <div className="bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Hora</th>
                                <th className="px-6 py-3.5">Cliente</th>
                                <th className="px-6 py-3.5">Cobrado Por</th>
                                <th className="px-6 py-3.5 text-center">Método de Pago</th>
                                <th className="px-6 py-3.5 text-center">Estado</th>
                                <th className="px-6 py-3.5 text-right">Monto Recaudado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {payments.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-slate-400">
                                        No se registraron cobros con los filtros seleccionados.
                                    </td>
                                </tr>
                            ) : (
                                payments.data.map((payment) => (
                                    <tr key={payment.id} className="hover:bg-slate-50/80 transition">
                                        <td className="px-6 py-4 text-slate-500 font-medium whitespace-nowrap">
                                            {payment.paid_at ? new Date(payment.paid_at).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }) : '-'} hs
                                        </td>
                                        <td className="px-6 py-4">
                                            {payment.customer ? (
                                                <Link
                                                    href={route('customers.show', payment.customer.id)}
                                                    className="font-semibold text-slate-900 hover:text-blue-600 transition"
                                                >
                                                    {payment.customer.name}
                                                </Link>
                                            ) : (
                                                <span className="text-slate-400 italic">Cliente sin identificar</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-slate-700">
                                            {payment.visit?.repartidor?.user?.name || payment.recipient?.name || 'Administración'}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <PaymentMethodBadge method={payment.payment_method?.name || 'Efectivo'} />
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <StatusBadge status={payment.status === 'CONFIRMED' ? 'FINALIZADO' : 'CANCELADO'} label={payment.status === 'CONFIRMED' ? 'Confirmado' : 'Cancelado'} />
                                        </td>
                                        <td className="px-6 py-4 text-right font-bold text-sm text-emerald-600">
                                            +${Number(payment.amount).toLocaleString('es-AR')}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {payments.last_page > 1 && (
                    <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>
                            Página {payments.current_page} de {payments.last_page} ({payments.total} cobros)
                        </span>
                        <div className="flex items-center gap-1">
                            {payments.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveState
                                    className={`px-3 py-1.5 rounded-md border text-xs font-medium transition ${
                                        link.active
                                            ? 'bg-blue-600 text-white border-blue-600 font-bold'
                                            : !link.url
                                            ? 'text-slate-300 border-slate-100 pointer-events-none'
                                            : 'text-slate-700 border-slate-200 hover:bg-slate-50'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
