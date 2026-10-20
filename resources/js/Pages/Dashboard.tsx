import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    KpiCard,
    StatusBadge,
} from '@/Components/StitchUi';
import {
    DollarSign,
    MapPin,
    Truck,
    Package,
    ArrowRight,
    CircleDollarSign,
    CheckCircle2,
    RefreshCw,
} from 'lucide-react';

interface RepartidorStatus {
    id: number;
    name: string;
    status: string;
    totalStops: number;
    completedStops: number;
    progressPercent: number;
    startedAt?: string;
    completedAt?: string;
}

interface DashboardMetrics {
    date: string;
    kpis: {
        totalSales: number;
        salesDelta: number;
        totalCollected: number;
        cashPayments: number;
        transferPayments: number;
        totalStops: number;
        completedStops: number;
        stopsProgressPercent: number;
        containersDelivered: number;
        containersReturned: number;
    };
    repartidores: RepartidorStatus[];
}

interface Props {
    metrics: DashboardMetrics;
}

export default function Dashboard({ metrics }: Props) {
    const { kpis, repartidores } = metrics;

    const showDay = (date: string) => {
        if (!date) return;
        router.get(route('dashboard'), { date }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCashClose = (driverId: number) => {
        router.visit(route('cash-close.index', { repartidor_id: driverId, date: metrics.date }));
    };

    const formattedSales = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0,
    }).format(kpis.totalSales);

    const formattedCollected = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0,
    }).format(kpis.totalCollected);

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-slate-900">
                        Dashboard
                    </span>
                </div>
            }
        >
            <Head title="Panel de Control - Marilin" />

            {/* Page Header */}
            <div className="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">
                        Dashboard
                    </h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Resumen operativo del día: <span className="font-medium text-slate-700">{metrics.date}</span>
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="date"
                        value={metrics.date}
                        onChange={(e) => showDay(e.target.value)}
                        aria-label="Día del resumen"
                        style={{ colorScheme: 'light' }}
                        className="py-2 px-3 text-sm font-medium border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-transparent bg-white text-slate-900 shadow-sm"
                    />
                    <button
                        onClick={() => router.reload({ only: ['metrics'] })}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all shadow-[0_1px_2px_rgba(0,0,0,0.05)] ring-1 ring-inset ring-slate-200/80 cursor-pointer"
                    >
                        <RefreshCw className="w-4 h-4 text-slate-400" strokeWidth={2} />
                        Actualizar
                    </button>
                </div>
            </div>

            {/* 1. KPIs Row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <KpiCard
                    title="Ventas del Día"
                    value={formattedSales}
                    icon={DollarSign}
                    variant="primary"
                    trend={{
                        value: `${kpis.salesDelta > 0 ? '+' : ''}${kpis.salesDelta}%`,
                        isPositive: kpis.salesDelta >= 0,
                        label: 'vs. ayer',
                    }}
                />

                <KpiCard
                    title="Cobranza Recaudada"
                    value={formattedCollected}
                    icon={CircleDollarSign}
                    variant="success"
                    subtitle={`EFE: $${kpis.cashPayments.toLocaleString('es-AR')} | TRF: $${kpis.transferPayments.toLocaleString('es-AR')}`}
                />

                <KpiCard
                    title="Paradas de Ruta"
                    value={`${kpis.completedStops} / ${kpis.totalStops}`}
                    icon={MapPin}
                    variant="accent"
                    trend={{
                        value: `${kpis.stopsProgressPercent}%`,
                        isPositive: true,
                        label: 'completadas',
                    }}
                />

                <KpiCard
                    title="Movimiento Envases"
                    value={`+${kpis.containersDelivered} / -${kpis.containersReturned}`}
                    icon={Package}
                    variant="slate"
                    subtitle="Entregados vs retornados"
                />
            </div>

            {/* 2. Main Content Grid: Repartidores & Actions */}
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                {/* Repartidores En Ruta Table */}
                <div className="lg:col-span-3 bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden">
                    <div className="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="text-base font-semibold text-slate-900">
                            Repartidores en ruta
                        </h2>
                        <span className="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                            {repartidores.length} activos
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="bg-slate-50/50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-3 text-xs font-medium text-slate-500 w-1/4">
                                        Repartidor
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-slate-500 w-1/4">
                                        Progreso
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-slate-500 w-1/4">
                                        Estado
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-slate-500 w-1/4 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-100">
                                {repartidores.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-8 text-center text-sm font-medium text-slate-400">
                                            No hay repartidores activos en este momento.
                                        </td>
                                    </tr>
                                ) : (
                                    repartidores.map((rep) => (
                                        <tr
                                            key={rep.id}
                                            onClick={() => openCashClose(rep.id)}
                                            className="hover:bg-slate-50 transition-colors cursor-pointer"
                                            title="Ver cierre de caja del día"
                                        >
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 bg-slate-100 text-slate-700 flex items-center justify-center font-semibold text-xs rounded-full">
                                                        {rep.name.substring(0,2).toUpperCase()}
                                                    </div>
                                                    <span className="font-medium text-slate-900">{rep.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-slate-900">{rep.completedStops} / {rep.totalStops}</span>
                                                        <span className="text-xs text-slate-500">Paradas</span>
                                                    </div>
                                                    <div className="w-24 h-2 bg-slate-100 rounded-full overflow-hidden">
                                                        <div
                                                            className="h-full bg-blue-600 transition-all duration-300 rounded-full"
                                                            style={{ width: `${rep.progressPercent}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <StatusBadge status={rep.status} />
                                                    {rep.startedAt && (
                                                        <span className="text-xs text-slate-500">
                                                            desde {rep.startedAt}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={route('cash-close.index', { repartidor_id: rep.id, date: metrics.date })}
                                                    onClick={(e) => e.stopPropagation()}
                                                    aria-label={`Cierre de caja de ${rep.name}`}
                                                    className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-gray-200/60 hover:bg-slate-50 text-slate-600 transition-colors shadow-sm cursor-pointer"
                                                >
                                                    <ArrowRight className="w-4 h-4" strokeWidth={2} />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Quick Actions (1 Col) */}
                <div className="space-y-6">
                    <div className="bg-white rounded-2xl border border-gray-200/60 p-6 shadow-sm">
                        <h3 className="text-sm font-semibold text-slate-900 mb-4">
                            Acciones rápidas
                        </h3>
                        <div className="flex flex-col gap-3">
                            <Link
                                href={route('routes.index')}
                                className="w-full flex items-center justify-between px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white transition-all rounded-lg text-sm font-medium shadow-[0_1px_2px_rgba(0,0,0,0.1),inset_0_1px_0_rgba(255,255,255,0.2)] ring-1 ring-inset ring-blue-700/50 cursor-pointer"
                            >
                                <span className="flex items-center gap-2">
                                    <Truck className="w-4 h-4 text-blue-100" strokeWidth={2} />
                                    Asignar ruta
                                </span>
                                <ArrowRight className="w-4 h-4 text-blue-200" strokeWidth={2} />
                            </Link>
                            <Link
                                href={route('cash-close.index', { date: metrics.date })}
                                className="w-full flex items-center justify-between px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-700 transition-all rounded-lg text-sm font-medium shadow-[0_1px_2px_rgba(0,0,0,0.05)] ring-1 ring-inset ring-slate-200/80 cursor-pointer"
                            >
                                <span className="flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-slate-400" strokeWidth={2} />
                                    Cierre de caja
                                </span>
                                <ArrowRight className="w-4 h-4 text-slate-300" strokeWidth={2} />
                            </Link>
                        </div>
                    </div>

                    {/* Stock / System Status */}
                    <div className="bg-white rounded-2xl border border-gray-200/60 p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-semibold text-slate-900">
                                Sistema
                            </span>
                            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        </div>
                        <p className="text-xs text-slate-500 leading-relaxed mt-2">
                            Todos los servicios operativos. Sincronización en tiempo real activa.
                        </p>
                    </div>
                </div>

            </div>
        </AdminLayout>
    );
}

