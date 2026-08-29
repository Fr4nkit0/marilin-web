import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, KpiCard, PaymentMethodBadge } from '@/Components/StitchUi';
import {
    Search,
    Receipt,
    DollarSign,
    CreditCard,
    Calendar,
    Filter,
    ArrowUpRight,
    User,
    Clock,
} from 'lucide-react';

interface SaleDetail {
    id: number;
    product?: {
        name: string;
    };
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
}

interface SaleItem {
    id: number;
    date: string;
    total: string | number;
    is_credit_sale: boolean;
    customer?: {
        id: number;
        name: string;
        primary_address?: {
            address_line: string;
        };
    };
    visit?: {
        repartidor?: {
            user?: {
                name: string;
            };
        };
    };
    details?: SaleDetail[];
    payments?: {
        payment_method?: {
            name: string;
        };
    }[];
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
    sales: PaginatedData<SaleItem>;
    metrics: {
        totalSalesAmount: number;
        creditSalesAmount: number;
        immediateSalesAmount: number;
        count: number;
    };
    filters: {
        search?: string;
        date: string;
        repartidor_id?: string;
        sale_type?: string;
    };
    repartidores: { id: number; name: string }[];
}

export default function SalesIndex({ sales, metrics, filters, repartidores }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [date, setDate] = useState(filters.date || '');
    const [repartidorId, setRepartidorId] = useState(filters.repartidor_id || '');
    const [saleType, setSaleType] = useState(filters.sale_type || 'all');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('sales.index'),
            {
                search: search || undefined,
                date: date || undefined,
                repartidor_id: repartidorId || undefined,
                sale_type: saleType !== 'all' ? saleType : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleFilterChange = (key: string, value: string) => {
        const newFilters = {
            search,
            date: key === 'date' ? value : date,
            repartidor_id: key === 'repartidor_id' ? value : repartidorId,
            sale_type: key === 'sale_type' ? value : saleType,
        };

        if (key === 'date') setDate(value);
        if (key === 'repartidor_id') setRepartidorId(value);
        if (key === 'sale_type') setSaleType(value);

        router.get(
            route('sales.index'),
            {
                search: newFilters.search || undefined,
                date: newFilters.date || undefined,
                repartidor_id: newFilters.repartidor_id || undefined,
                sale_type: newFilters.sale_type !== 'all' ? newFilters.sale_type : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Facturación
                    </span>
                </div>
            }
        >
            <Head title="Reporte de Ventas - Marilin" />

            <PageHeader
                title="Reporte de Ventas"
                description={`Registro de ventas realizadas en calle y mostrador para la fecha ${filters.date}.`}
            />

            {/* Metrics Row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <KpiCard
                    title="Ventas Totales"
                    value={`$${metrics.totalSalesAmount.toLocaleString('es-AR')}`}
                    icon={DollarSign}
                    variant="primary"
                    subtitle={`${metrics.count} operaciones registradas`}
                />
                <KpiCard
                    title="Contado / Cobrado"
                    value={`$${metrics.immediateSalesAmount.toLocaleString('es-AR')}`}
                    icon={Receipt}
                    variant="success"
                    subtitle="Efectivo o Transferencias"
                />
                <KpiCard
                    title="A Crédito / Cta Cte"
                    value={`$${metrics.creditSalesAmount.toLocaleString('es-AR')}`}
                    icon={CreditCard}
                    variant="amber"
                    subtitle="Pendientes de cobro"
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

                    {/* Repartidor */}
                    <div className="sm:col-span-3">
                        <select
                            value={repartidorId}
                            onChange={(e) => handleFilterChange('repartidor_id', e.target.value)}
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                        >
                            <option value="">Todos los Repartidores</option>
                            {repartidores.map((rep) => (
                                <option key={rep.id} value={rep.id}>
                                    {rep.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Sale Type */}
                    <div className="sm:col-span-2">
                        <select
                            value={saleType}
                            onChange={(e) => handleFilterChange('sale_type', e.target.value)}
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                        >
                            <option value="all">Todo tipo</option>
                            <option value="cash_immediate">Contado</option>
                            <option value="credit">A Crédito</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Sales Table */}
            <div className="bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Fecha / Hora</th>
                                <th className="px-6 py-3.5">Cliente</th>
                                <th className="px-6 py-3.5">Repartidor</th>
                                <th className="px-6 py-3.5">Detalle Productos</th>
                                <th className="px-6 py-3.5 text-center">Tipo / Método</th>
                                <th className="px-6 py-3.5 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {sales.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-slate-400">
                                        No se encontraron ventas con los filtros seleccionados.
                                    </td>
                                </tr>
                            ) : (
                                sales.data.map((sale) => {
                                    const paymentMethodName = sale.payments?.[0]?.payment_method?.name || (sale.is_credit_sale ? 'Crédito Ruta' : 'Efectivo');
                                    return (
                                        <tr key={sale.id} className="hover:bg-slate-50/80 transition">
                                            <td className="px-6 py-4 text-slate-500 font-medium whitespace-nowrap">
                                                {new Date(sale.date).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })} hs
                                            </td>
                                            <td className="px-6 py-4">
                                                {sale.customer ? (
                                                    <Link
                                                        href={route('customers.show', sale.customer.id)}
                                                        className="font-semibold text-slate-900 hover:text-blue-600 transition"
                                                    >
                                                        {sale.customer.name}
                                                    </Link>
                                                ) : (
                                                    <span className="text-slate-400 italic">Consumidor Final</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-slate-700">
                                                {sale.visit?.repartidor?.user?.name || '-'}
                                            </td>
                                            <td className="px-6 py-4 text-slate-600">
                                                {sale.details && sale.details.length > 0 ? (
                                                    <div className="space-y-0.5">
                                                        {sale.details.map((d) => (
                                                            <div key={d.id} className="text-[11px]">
                                                                {d.quantity}x {d.product?.name}
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-center">
                                                <PaymentMethodBadge method={paymentMethodName} />
                                            </td>
                                            <td className="px-6 py-4 text-right font-bold text-sm text-slate-900">
                                                ${Number(sale.total).toLocaleString('es-AR')}
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {sales.last_page > 1 && (
                    <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>
                            Página {sales.current_page} de {sales.last_page} ({sales.total} ventas)
                        </span>
                        <div className="flex items-center gap-1">
                            {sales.links.map((link, idx) => (
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
