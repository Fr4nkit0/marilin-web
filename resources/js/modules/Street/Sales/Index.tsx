import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { PaymentMethodBadge, StatusBadge } from '@/ui/Badge';
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
    CircleDollarSign,
} from 'lucide-react';
import { date, money, paymentMethodsLabel, time } from '@/lib/format';
import { useListFilters } from '@/lib/useListFilters';
import DataTable, { type Column } from '@/ui/DataTable';
import Pagination from '@/ui/Pagination';
import type { PaginatedData } from '@/types';
import { DriverFilterSelect, SalesPaymentsTabs } from '../components/SalesPaymentsNav';
import CancelRecordModal, { type CancelTarget } from '../components/CancelRecordModal';
import Button from '@/ui/Button';
import type { LinkedPayment } from '../types';

interface SaleDetail {
    id: number;
    product?: {
        name: string;
    };
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
}

/** A sale is confirmed until the driver (from the app) or the admin (from here) cancels it; "annulled" is a stored value no flow writes today. */
type SaleStatus = 'confirmed' | 'cancelled' | 'annulled';

interface SaleItem {
    id: number;
    date: string;
    total: string | number;
    status: SaleStatus;
    is_credit_sale: boolean;
    customer?: {
        id: number;
        name: string;
        primary_address?: {
            address_line: string;
        };
    };
    visit?: {
        driver?: {
            user?: {
                name: string;
            };
        };
    } | null;
    creator?: {
        name: string;
    } | null;
    details?: SaleDetail[];
    /** Every method the sale was paid with, in the order paid, without repeats. */
    payment_methods: string[];
    /** The sale's payments still in force: cancelling the sale does not cancel them. */
    active_payments: LinkedPayment[];
}

interface Props {
    sales: PaginatedData<SaleItem>;
    metrics: {
        totalSalesAmount: number;
        creditSalesAmount: number;
        /** "Contado": what was sold not on account. A sale figure, not what was collected. */
        immediateSalesAmount: number;
        /** "Cobrado": the day's confirmed payments of every method, the same figure the panel and the cash close show. */
        collectedAmount: number;
        count: number;
    };
    /** Only the admin cancels a sale from the web. */
    canCancel: boolean;
    filters: {
        search?: string;
        date: string;
        repartidor_id?: string;
        sale_type?: string;
    };
    repartidores: { id: number; name: string }[];
}

export default function SalesIndex({ sales, metrics, filters, repartidores, canCancel }: Props) {
    const [cancelling, setCancelling] = useState<CancelTarget | null>(null);

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
            // A long customer name wraps so the row actions stay inside the table at 1440 px.
            className: 'whitespace-normal min-w-[10rem]',
            cell: (sale) =>
                sale.customer ? (
                    <Link href={route('customers.show', sale.customer.id)} className="font-medium text-ink hover:text-ink-secondary transition">
                        {sale.customer.name}
                    </Link>
                ) : (
                    <span className="text-ink-subtle font-medium italic">Consumidor Final</span>
                ),
        },
        { header: 'Registrada por', className: 'text-ink-secondary', cell: (sale) => sale.visit?.driver?.user?.name || sale.creator?.name || '-' },
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
            cell: (sale) =>
                sale.status === 'confirmed' ? (
                    <PaymentMethodBadge method={paymentMethodsLabel(sale.payment_methods) || (sale.is_credit_sale ? 'Crédito Ruta' : 'Efectivo')} />
                ) : (
                    <StatusBadge status="cancelado" label="Anulada" />
                ),
        },
        {
            header: 'Total',
            align: 'right',
            className: 'font-semibold text-ink',
            // A cancelled sale moved no money: its amount stays visible but struck through, outside the totals.
            cell: (sale) => (sale.status === 'confirmed' ? money(sale.total) : <s className="font-normal text-ink-muted">{money(sale.total)}</s>),
        },
        ...(canCancel
            ? [
                  {
                      header: <span className="sr-only">Acciones</span>,
                      align: 'right',
                      cell: (sale: SaleItem) =>
                          sale.status === 'confirmed' ? (
                              <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() =>
                                      setCancelling({
                                          url: route('sales.cancel', sale.id),
                                          title: `Anular venta #${sale.id}`,
                                          summary: `${sale.customer?.name ?? 'Consumidor Final'} · ${money(sale.total)} · ${date(sale.date)}`,
                                          linkedPayments: sale.active_payments,
                                      })
                                  }
                              >
                                  Anular
                              </Button>
                          ) : null,
                  } satisfies Column<SaleItem>,
              ]
            : []),
    ];

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink-muted">
                        Ventas y cobros
                    </span>
                </div>
            }
        >
            <Head title="Ventas y cobros" />

            <PageHeader
                title="Ventas y cobros"
                description={`Ventas hechas en la calle y en casa el ${date(filters.date)}.`}
            />

            <SalesPaymentsTabs current="sales" filters={{ search: filters.search, date: filters.date, repartidor_id: filters.repartidor_id }} />

            {/* Metrics Row */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
                <KpiCard
                    title="Ventas Totales"
                    value={money(metrics.totalSalesAmount)}
                    icon={DollarSign}
                    variant="primary"
                    subtitle={metrics.count === 1 ? '1 operación registrada' : `${metrics.count} operaciones registradas`}
                />
                <KpiCard
                    title="Contado"
                    value={money(metrics.immediateSalesAmount)}
                    icon={Receipt}
                    variant="success"
                    subtitle="Ventas que no van a cuenta corriente"
                />
                <KpiCard
                    title="Cobrado"
                    value={money(metrics.collectedAmount)}
                    icon={CircleDollarSign}
                    variant="accent"
                    subtitle="Cobros del día: efectivo, transferencia y tarjeta"
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
                            aria-label="Buscar cliente"
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
                            aria-label="Fecha"
                            style={{ colorScheme: 'light' }}
                            className="w-full py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all"
                        />
                    </div>

                    {/* Repartidor */}
                    <div className="sm:col-span-3">
                        <DriverFilterSelect
                            value={filter.values.repartidor_id}
                            onChange={(value) => filter.set('repartidor_id', value)}
                            drivers={repartidores}
                        />
                    </div>

                    {/* Sale Type */}
                    <div className="sm:col-span-2">
                        <select
                            value={filter.values.sale_type}
                            onChange={(e) => filter.set('sale_type', e.target.value)}
                            aria-label="Tipo de venta"
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

            <CancelRecordModal
                target={cancelling}
                consequence="Se devuelve el total a la cuenta corriente del cliente, el stock a la ruta y los envases que se movieron con la venta. Los cobros de la venta no se tocan."
                onClose={() => setCancelling(null)}
            />
        </AdminLayout>
    );
}
