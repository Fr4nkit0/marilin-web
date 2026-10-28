import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { PaymentMethodBadge, StatusBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import { PageHeader } from '@/ui/PageHeader';
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
import { money, paymentStatusLabel, time, type PaymentStatus } from '@/lib/format';
import { useListFilters } from '@/lib/useListFilters';
import DataTable, { type Column } from '@/ui/DataTable';
import Pagination from '@/ui/Pagination';
import type { PaginatedData } from '@/types';

interface PaymentItem {
    id: number;
    amount: string | number;
    paid_at: string;
    status: PaymentStatus;
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
    const filter = useListFilters(
        'payments.index',
        {
            search: filters.search || '',
            date: filters.date || '',
            payment_method_id: filters.payment_method_id || '',
            status: filters.status || 'all',
        },
        { search: '', date: '', payment_method_id: '', status: 'all' },
    );

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        filter.submit();
    };

    const columns: Column<PaymentItem>[] = [
        { header: 'Hora', className: 'text-slate-500 whitespace-nowrap', cell: (payment) => `${payment.paid_at ? time(payment.paid_at) : '-'} hs` },
        {
            header: 'Cliente',
            cell: (payment) =>
                payment.customer ? (
                    <Link href={route('customers.show', payment.customer.id)} className="font-medium text-slate-900 hover:text-slate-600 transition">
                        {payment.customer.name}
                    </Link>
                ) : (
                    <span className="text-slate-400 font-medium italic">Cliente sin identificar</span>
                ),
        },
        { header: 'Cobrado Por', className: 'text-slate-700', cell: (payment) => payment.visit?.repartidor?.user?.name || payment.recipient?.name || 'Administración' },
        { header: 'Método de Pago', align: 'center', cell: (payment) => <PaymentMethodBadge method={payment.payment_method?.name || 'Efectivo'} /> },
        {
            header: 'Estado',
            align: 'center',
            cell: (payment) => (
                <StatusBadge status={payment.status === 'CONFIRMED' ? 'FINALIZADO' : 'CANCELADO'} label={paymentStatusLabel(payment.status)} />
            ),
        },
        { header: 'Monto Recaudado', align: 'right', className: 'font-semibold text-slate-900', cell: (payment) => `+${money(payment.amount)}` },
    ];

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-slate-500">
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
                    value={money(metrics.totalCollected)}
                    icon={CircleDollarSign}
                    variant="success"
                    subtitle="Cobranza confirmada hoy"
                />
                <KpiCard
                    title="Efectivo en Mano"
                    value={money(metrics.cashTotal)}
                    icon={DollarSign}
                    variant="primary"
                    subtitle="A rendir por los repartidores"
                />
                <KpiCard
                    title="Transferencias Bancarias"
                    value={money(metrics.transferTotal)}
                    icon={CheckCircle2}
                    variant="accent"
                    subtitle="Acreditadas en cuenta"
                />
            </div>

            {/* Filters */}
            <div className="bg-white border border-gray-200/60 rounded-2xl p-4 mb-6 shadow-sm">
                <form onSubmit={handleSearch} className="grid grid-cols-1 gap-3 sm:grid-cols-12 items-center">
                    {/* Search */}
                    <div className="sm:col-span-4 relative">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={filter.values.search}
                            onChange={(e) => filter.draft('search', e.target.value)}
                            placeholder="Buscar por cliente..."
                            className="w-full pl-9 pr-4 py-2 text-sm font-medium border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-white text-slate-900 placeholder-gray-400 shadow-sm transition-all"
                        />
                    </div>

                    {/* Date */}
                    <div className="sm:col-span-3">
                        <input
                            type="date"
                            value={filter.values.date}
                            onChange={(e) => filter.set('date', e.target.value)}
                            style={{ colorScheme: 'light' }}
                            className="w-full py-2 px-3 text-sm font-medium border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-white text-slate-900 shadow-sm transition-all"
                        />
                    </div>

                    {/* Payment Method */}
                    <div className="sm:col-span-3">
                        <select
                            value={filter.values.payment_method_id}
                            onChange={(e) => filter.set('payment_method_id', e.target.value)}
                            className="w-full py-2 px-3 text-sm font-medium border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-white text-slate-900 shadow-sm transition-all"
                        >
                            <option value="">Todos los métodos</option>
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
                            value={filter.values.status}
                            onChange={(e) => filter.set('status', e.target.value)}
                            className="w-full py-2 px-3 text-sm font-medium border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-white text-slate-900 shadow-sm transition-all"
                        >
                            <option value="all">Todos los estados</option>
                            <option value="CONFIRMED">Confirmados</option>
                            <option value="CANCELLED">Cancelados</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Payments Table */}
            <div className="bg-white border border-gray-200/60 rounded-2xl shadow-sm overflow-hidden">
                <DataTable columns={columns} rows={payments.data} rowKey={(payment) => payment.id} empty="No se registraron cobros con los filtros seleccionados." />
                <Pagination page={payments} noun="cobros" />
            </div>
        </AdminLayout>
    );
}
