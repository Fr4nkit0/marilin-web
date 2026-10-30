import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import { PageHeader } from '@/ui/PageHeader';
import CashCloseDetailModal from './components/CashCloseDetailModal';
import { CashCloseDetailKind, SettlementData } from './types';
import {
    CircleDollarSign,
    DollarSign,
    Package,
    Truck,
    CheckCircle2,
    Calendar,
    AlertCircle,
    ArrowUpRight,
    TrendingUp,
    FileText,
    FileSpreadsheet,
} from 'lucide-react';
import { date, money } from '@/lib/format';

interface Props {
    repartidores: { id: number; name: string }[];
    selectedRepartidorId?: number;
    settlement?: SettlementData | null;
    date: string;
}

export default function CashCloseIndex({
    repartidores,
    selectedRepartidorId,
    settlement,
    date: day,
}: Props) {
    const [selectedId, setSelectedId] = useState(selectedRepartidorId || '');
    const [selectedDate, setSelectedDate] = useState(day);
    const [openDetail, setOpenDetail] = useState<CashCloseDetailKind | null>(null);

    const handleSelectRepartidor = (id: string | number) => {
        setSelectedId(id);
        router.get(
            route('cash-close.index'),
            { repartidor_id: id || undefined, date: selectedDate },
            { preserveState: true, replace: true }
        );
    };

    const handleDateChange = (newDate: string) => {
        setSelectedDate(newDate);
        router.get(
            route('cash-close.index'),
            { repartidor_id: selectedId || undefined, date: newDate },
            { preserveState: true, replace: true }
        );
    };

    const activeRepartidorName = repartidores.find((r) => r.id === Number(selectedId))?.name || 'Seleccione repartidor';

    const exportUrl = (format: 'pdf' | 'xlsx') =>
        route('cash-close.export', { format, repartidor_id: selectedId, date: selectedDate });

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink-muted">
                        Cierre de caja
                    </span>
                </div>
            }
        >
            <Head title="Cierre de caja" />

            <PageHeader
                title="Cierre de caja"
                description={`Arqueo de la ruta: conciliación de efectivo, transferencias y balance de stock retornado del ${date(selectedDate)}.`}
            />

            {/* Drivers Selection Toolbar */}
            <div className="bg-canvas border border-hairline/60 rounded-2xl p-4 mb-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4 ">
                <div className="flex items-center gap-3 w-full sm:w-auto">
                    <label htmlFor="cash-close-driver" className="text-sm font-medium text-ink-muted whitespace-nowrap">
                        Repartidor:
                    </label>
                    <select
                        id="cash-close-driver"
                        value={selectedId}
                        onChange={(e) => handleSelectRepartidor(e.target.value)}
                        className="py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all min-w-[200px]"
                    >
                        {repartidores.map((rep) => (
                            <option key={rep.id} value={rep.id}>
                                {rep.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-3 w-full sm:w-auto">
                    <label htmlFor="cash-close-date" className="text-sm font-medium text-ink-muted whitespace-nowrap">
                        Fecha Jornada:
                    </label>
                    <input
                        id="cash-close-date"
                        type="date"
                        value={selectedDate}
                        onChange={(e) => handleDateChange(e.target.value)}
                        style={{ colorScheme: 'light' }}
                        className="py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm transition-all"
                    />
                </div>

                {settlement && (
                    <div className="flex items-center gap-2 w-full sm:w-auto">
                        <a
                            href={exportUrl('pdf')}
                            className="inline-flex items-center gap-2 py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg bg-canvas text-ink-secondary shadow-sm transition-all hover:bg-canvas-soft focus:outline-none focus:ring-2 focus:ring-sky-600"
                        >
                            <FileText className="w-4 h-4" aria-hidden="true" />
                            Descargar PDF
                        </a>
                        <a
                            href={exportUrl('xlsx')}
                            className="inline-flex items-center gap-2 py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg bg-canvas text-ink-secondary shadow-sm transition-all hover:bg-canvas-soft focus:outline-none focus:ring-2 focus:ring-sky-600"
                        >
                            <FileSpreadsheet className="w-4 h-4" aria-hidden="true" />
                            Descargar Excel
                        </a>
                    </div>
                )}
            </div>

            {!settlement ? (
                <div className="bg-canvas border border-hairline/60 rounded-2xl p-12 text-center text-ink-muted shadow-sm ">
                    <AlertCircle className="w-12 h-12 mx-auto text-ink-subtle mb-3" />
                    <h3 className="text-lg font-semibold text-ink">Sin ruta para liquidar</h3>
                    <p className="text-sm text-ink-muted mt-2">
                        El repartidor <strong className="font-medium text-ink">{activeRepartidorName}</strong> no tiene una ruta registrada en la fecha {date(selectedDate)}.
                    </p>
                </div>
            ) : (
                <div className="space-y-6">
                    {/* Settlement KPI Row */}
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <KpiCard
                            title="Total Rendición Efectivo"
                            onClick={() => setOpenDetail('cash')}
                            value={money(settlement.cashAmount)}
                            icon={DollarSign}
                            variant="primary"
                            subtitle="Dinero físico a ingresar en tesorería"
                        />
                        <KpiCard
                            title="Transferencias Verificadas"
                            onClick={() => setOpenDetail('transfer')}
                            value={money(settlement.transferAmount)}
                            icon={CheckCircle2}
                            variant="success"
                            subtitle="Acreditado en cuenta bancaria"
                        />
                        <KpiCard
                            title="Ventas a Crédito (Cta Cte)"
                            onClick={() => setOpenDetail('credit')}
                            value={money(settlement.creditSales)}
                            icon={CircleDollarSign}
                            variant="amber"
                            subtitle="Suma al saldo deudor de clientes"
                        />
                        <KpiCard
                            title="Balance Envases Retornables"
                            onClick={() => setOpenDetail('containers')}
                            value={`+${settlement.containersDelivered} / -${settlement.containersReturned}`}
                            icon={Package}
                            variant="accent"
                            subtitle="Entregados vs. Devueltos a base"
                        />
                    </div>

                    {/* Reconciliation Breakdown: Money & Physical Stock */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Money Reconciliation Box */}
                        <div className="bg-canvas border border-hairline/60 rounded-2xl p-6 shadow-sm ">
                            <h3 className="text-base font-semibold text-ink mb-6 flex items-center justify-between">
                                <span>Arqueo Monetario de la Jornada</span>
                                <StatusBadge status={settlement.status} />
                            </h3>

                            <div className="space-y-3 text-sm">
                                <div className="flex justify-between py-2 border-b border-hairline last:border-0">
                                    <span className="text-ink-secondary font-medium">Total Ventas Brutas de la Ruta:</span>
                                    <span className="font-semibold text-ink">{money(settlement.totalSales)}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b border-hairline last:border-0">
                                    <span className="text-ink-secondary font-medium">Menos: Ventas Fiadas / Crédito:</span>
                                    <span className="font-semibold text-ink-muted">-{money(settlement.creditSales)}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b border-hairline last:border-0">
                                    <span className="text-ink-secondary font-medium">Cobranza de Cuentas Corrientes previas:</span>
                                    <span className="font-semibold text-ink">+{money(settlement.debtCollected)}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b border-hairline last:border-0">
                                    <span className="text-ink-secondary font-medium">Menos: Transferencias:</span>
                                    <span className="font-semibold text-ink-muted">-{money(settlement.transferAmount)}</span>
                                </div>
                                <div className="flex justify-between items-center py-4 bg-canvas-soft border border-hairline px-5 rounded-xl mt-4 shadow-sm">
                                    <span className="text-ink-secondary font-medium">Efectivo Físico a Entregar:</span>
                                    <span className="text-ink font-semibold text-lg">{money(settlement.cashAmount)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Inventory Reconciliation Box */}
                        <div className="bg-canvas border border-hairline/60 rounded-2xl p-6 shadow-sm ">
                            <h3 className="text-base font-semibold text-ink mb-6 flex items-center justify-between">
                                <span>Inventario de ruta y envases</span>
                                <Package className="w-5 h-5 text-ink-subtle" />
                            </h3>

                            <div className="space-y-3">
                                {settlement.stockLoaded.length === 0 ? (
                                    <p className="text-sm text-ink-muted py-4 text-center">
                                        No hay registros de inventario para esta ruta.
                                    </p>
                                ) : (
                                    settlement.stockLoaded.map((load, idx) => {
                                        const remaining = settlement.stockRemaining.find((r) => r.product === load.product)?.quantity || 0;
                                        const sold = load.quantity - remaining;

                                        return (
                                            <div
                                                key={idx}
                                                className="p-4 bg-canvas border border-hairline/60 rounded-xl flex items-center justify-between shadow-sm transition-all hover:bg-canvas-soft/50 "
                                            >
                                                <div>
                                                    <p className="font-medium text-ink text-sm">{load.product}</p>
                                                    <p className="text-xs text-ink-muted mt-1">
                                                        Cargado en el día: {load.quantity} unid.
                                                        {load.loads > 1 && ` (${load.loads} cargas)`}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-semibold text-ink text-sm">
                                                        {sold} vendidos
                                                    </p>
                                                    <p className="text-xs text-ink-muted mt-1">
                                                        Vuelven: {remaining} unid.
                                                    </p>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>
                    </div>

                    <CashCloseDetailModal
                        kind={openDetail}
                        details={settlement.details}
                        onClose={() => setOpenDetail(null)}
                    />
                </div>
            )}
        </AdminLayout>
    );
}
