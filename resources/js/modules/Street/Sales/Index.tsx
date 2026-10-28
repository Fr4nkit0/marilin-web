import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { PaymentMethodBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import { PageHeader } from '@/ui/PageHeader';
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
import { money, time } from '@/lib/format';
import { useListFilters } from '@/lib/useListFilters';
import DataTable, { type Column } from '@/ui/DataTable';
import Pagination from '@/ui/Pagination';
import type { PaginatedData } from '@/types';

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
    const filter = useListFilters(
        'sales.index',
        {
            search: filters.search || '',
            date: filters.date || '',
            repartidor_id: filters.repartidor_id || '',
            sale_type: filters.sale_type || 'all',
        },
        { search: '', date: '', repartidor_id: '', sale_type: 'all' },
    );

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        filter.submit();
    };

    const columns: Column<SaleItem>[] = [
        { header: 'Fecha / Hora', className: 'text-ink-muted whitespace-nowrap', cell: (sale) => `${time(sale.date)} hs` },
        {
            header: 'Cliente',
            cell: (sale) =>
                sale.customer ? (
                    <Link href={route('customers.show', sale.customer.id)} className="font-medium text-ink hover:text-ink-secondary transition">
                        {sale.customer.name}
                    </Link>
                ) : (
                    <span className="text-ink-subtle font-medium italic">Consumidor Final</span>
                ),
        },
        { header: 'Repartidor', className: 'text-ink-secondary', cell: (sale) => sale.visit?.repartidor?.user?.name || '-' },
        {
            header: 'Detalle Productos',
            className: 'text-ink-secondary',
            cell: (sale) =>
                sale.details && sale.details.length > 0 ? (
                    <div className="space-y-1">
                        {sale.details.map((d) => (
                            <div key={d.id} className="text-xs">
                                {d.quantity}x {d.product?.name}
                            </div>
                        ))}
                    </div>
                ) : (
                    <span className="text-ink-subtle">-</span>
                ),
        },
        {
            header: 'Tipo / Método',
            align: 'center',
            cell: (sale) => <PaymentMethodBadge method={sale.payments?.[0]?.payment_method?.name || (sale.is_credit_sale ? 'Crédito Ruta' : 'Efectivo')} />,
        },
        { header: 'Total', align: 'right', className: 'font-semibold text-ink', cell: (sale) => money(sale.total) },
    ];

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink-muted">
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
                    value={money(metrics.totalSalesAmount)}
                    icon={DollarSign}
                    variant="primary"
                    subtitle={`${metrics.count} operaciones registradas`}
                />
                <KpiCard
                    title="Contado / Cobrado"
                    value={money(metrics.immediateSalesAmount)}
                    icon={Receipt}
                    variant="success"
                    subtitle="Efectivo o Transferencias"
                />
                <KpiCard
                    title="A Crédito / Cta Cte"
                    value={money(metrics.creditSalesAmount)}
                    icon={CreditCard}
                    variant="amber"
                    subtitle="Pendientes de cobro"
                />
            </div>

            {/* Filters */}
            <div className="bg-canvas border border-hairline/60 rounded-2xl p-4 mb-6 shadow-sm ">
                <form onSubmit={handleSearch} className="grid grid-cols-1 gap-3 sm:grid-cols-12 items-center">
                    {/* Search */}
                    <div className="sm:col-span-4 relative">
                        <Search className="w-4 h-4 text-ink-subtle absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={filter.values.search}
                            onChange={(e) => filter.draft('search', e.target.value)}
                            placeholder="Buscar por cliente..."
                            className="w-full pl-9 pr-4 py-2 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink placeholder-ink-subtle shadow-sm transition-all"
                        />
                    </div>

                    {/* Date */}
                    <div className="sm:col-span-3">
                        <input
                            type="date"
                            value={filter.values.date}
                            onChange={(e) => filter.set('date', e.target.value)}
                            style={{ colorScheme: 'light' }}
                            className="w-full py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all"
                        />
                    </div>

                    {/* Repartidor */}
                    <div className="sm:col-span-3">
                        <select
                            value={filter.values.repartidor_id}
                            onChange={(e) => filter.set('repartidor_id', e.target.value)}
                            className="w-full py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all"
                        >
                            <option value="">Todos los repartidores</option>
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
                            value={filter.values.sale_type}
                            onChange={(e) => filter.set('sale_type', e.target.value)}
                            className="w-full py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all"
                        >
                            <option value="all">Todo tipo</option>
                            <option value="cash_immediate">Contado</option>
                            <option value="credit">A crédito</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Sales Table */}
            <div className="bg-canvas border border-hairline/60 rounded-2xl shadow-sm overflow-hidden ">
                <DataTable columns={columns} rows={sales.data} rowKey={(sale) => sale.id} empty="No se encontraron ventas con los filtros seleccionados." />
                <Pagination page={sales} noun="ventas" />
            </div>
        </AdminLayout>
    );
}
