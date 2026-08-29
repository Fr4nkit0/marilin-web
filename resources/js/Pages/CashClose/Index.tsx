import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, KpiCard, StatusBadge } from '@/Components/StitchUi';
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
} from 'lucide-react';

interface SettlementData {
    routeId: number;
    status: string;
    totalSales: number;
    creditSales: number;
    cashAmount: number;
    transferAmount: number;
    totalCollected: number;
    containersDelivered: number;
    containersReturned: number;
    stockLoaded: { product: string; quantity: number }[];
    stockRemaining: { product: string; quantity: number }[];
}

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
    date,
}: Props) {
    const [selectedId, setSelectedId] = useState(selectedRepartidorId || '');
    const [selectedDate, setSelectedDate] = useState(date);

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

    const activeRepartidorName = repartidores.find((r) => r.id === Number(selectedId))?.name || 'Seleccione chofer';

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Liquidación & Arqueo
                    </span>
                </div>
            }
        >
            <Head title="Cierre de Caja y Arqueo - Marilin" />

            <PageHeader
                title="Cierre de Caja y Arqueo de Ruta"
                description={`Conciliación de dinero en efectivo, transferencias y balance de stock retornado para ${selectedDate}.`}
            />

            {/* Repartidores Selection Toolbar */}
            <div className="bg-white rounded-lg border border-slate-200 p-4 mb-6 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="flex items-center gap-3 w-full sm:w-auto">
                    <span className="text-xs font-semibold text-slate-700 whitespace-nowrap">
                        Repartidor:
                    </span>
                    <select
                        value={selectedId}
                        onChange={(e) => handleSelectRepartidor(e.target.value)}
                        className="py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white min-w-[200px]"
                    >
                        {repartidores.map((rep) => (
                            <option key={rep.id} value={rep.id}>
                                {rep.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-3 w-full sm:w-auto">
                    <span className="text-xs font-semibold text-slate-700 whitespace-nowrap">
                        Fecha Jornada:
                    </span>
                    <input
                        type="date"
                        value={selectedDate}
                        onChange={(e) => handleDateChange(e.target.value)}
                        className="py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                    />
                </div>
            </div>

            {!settlement ? (
                <div className="bg-white rounded-lg border border-slate-200 p-12 text-center text-slate-400">
                    <AlertCircle className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                    <h3 className="text-base font-bold text-slate-700">Sin ruta para liquidar</h3>
                    <p className="text-xs text-slate-500 mt-1">
                        El repartidor <strong>{activeRepartidorName}</strong> no tiene una ruta registrada en la fecha {selectedDate}.
                    </p>
                </div>
            ) : (
                <div className="space-y-6">
                    {/* Settlement KPI Row */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KpiCard
                            title="Total Rendición Efectivo"
                            value={`$${settlement.cashAmount.toLocaleString('es-AR')}`}
                            icon={DollarSign}
                            variant="primary"
                            subtitle="Dinero físico a ingresar en tesorería"
                        />
                        <KpiCard
                            title="Transferencias Verificadas"
                            value={`$${settlement.transferAmount.toLocaleString('es-AR')}`}
                            icon={CheckCircle2}
                            variant="success"
                            subtitle="Acreditado en cuenta bancaria"
                        />
                        <KpiCard
                            title="Ventas a Crédito (Cta Cte)"
                            value={`$${settlement.creditSales.toLocaleString('es-AR')}`}
                            icon={CircleDollarSign}
                            variant="amber"
                            subtitle="Suma al saldo deudor de clientes"
                        />
                        <KpiCard
                            title="Balance Envases Retornables"
                            value={`+${settlement.containersDelivered} / -${settlement.containersReturned}`}
                            icon={Package}
                            variant="accent"
                            subtitle="Entregados vs. Devueltos a base"
                        />
                    </div>

                    {/* Reconciliation Breakdown: Money & Physical Stock */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Money Reconciliation Box */}
                        <div className="bg-white rounded-lg border border-slate-200 p-6 shadow-2xs">
                            <h3 className="text-base font-bold font-display text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                                <span>Arqueo Monetario de la Jornada</span>
                                <StatusBadge status={settlement.status} />
                            </h3>

                            <div className="space-y-3 text-xs">
                                <div className="flex justify-between py-2 border-b border-slate-50">
                                    <span className="text-slate-600">Total Ventas Brutas de la Ruta:</span>
                                    <span className="font-bold text-slate-900">${settlement.totalSales.toLocaleString('es-AR')}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b border-slate-50">
                                    <span className="text-slate-600">Menos: Ventas Fiadas / Crédito:</span>
                                    <span className="font-semibold text-amber-600">-${settlement.creditSales.toLocaleString('es-AR')}</span>
                                </div>
                                <div className="flex justify-between py-2 border-b border-slate-50">
                                    <span className="text-slate-600">Cobranza de Cuentas Corrientes previas:</span>
                                    <span className="font-semibold text-blue-600">+${(settlement.totalCollected - (settlement.totalSales - settlement.creditSales)).toLocaleString('es-AR')}</span>
                                </div>
                                <div className="flex justify-between py-2.5 bg-blue-50/70 px-3 rounded-lg border border-blue-100 text-sm font-bold">
                                    <span className="text-blue-900">Efectivo Físico a Entregar:</span>
                                    <span className="text-blue-700">${settlement.cashAmount.toLocaleString('es-AR')}</span>
                                </div>
                            </div>
                        </div>

                        {/* Inventory Reconciliation Box */}
                        <div className="bg-white rounded-lg border border-slate-200 p-6 shadow-2xs">
                            <h3 className="text-base font-bold font-display text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                                <span>Control de Stock y Envases en Camión</span>
                                <Package className="w-5 h-5 text-slate-400" />
                            </h3>

                            <div className="space-y-3">
                                {settlement.stockLoaded.length === 0 ? (
                                    <p className="text-xs text-slate-400 py-4 text-center">
                                        No hay registros de inventario para esta ruta.
                                    </p>
                                ) : (
                                    settlement.stockLoaded.map((load, idx) => {
                                        const remaining = settlement.stockRemaining.find((r) => r.product === load.product)?.quantity || 0;
                                        const sold = load.quantity - remaining;

                                        return (
                                            <div
                                                key={idx}
                                                className="p-3 bg-slate-50 rounded-lg border border-slate-100 flex items-center justify-between text-xs"
                                            >
                                                <div>
                                                    <p className="font-bold text-slate-800">{load.product}</p>
                                                    <p className="text-[11px] text-slate-500">
                                                        Carga inicial: {load.quantity} unid.
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-bold text-emerald-600">
                                                        {sold} vendidos
                                                    </p>
                                                    <p className="text-[11px] text-slate-500">
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
                </div>
            )}
        </AdminLayout>
    );
}
