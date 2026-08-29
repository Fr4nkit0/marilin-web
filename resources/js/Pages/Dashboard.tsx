import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import {
    KpiCard,
    StatusBadge,
    PageHeader,
} from '@/Components/StitchUi';
import {
    DollarSign,
    MapPin,
    Truck,
    Package,
    ArrowUpRight,
    CircleDollarSign,
    CheckCircle2,
    Clock,
    RefreshCw,
    TrendingUp,
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
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Vista General
                    </span>
                </div>
            }
        >
            <Head title="Panel de Control - Marilin" />

            {/* Page Header */}
            <PageHeader
                title="Monitoreo de Operaciones"
                description={`Resumen operativo en tiempo real para la jornada de hoy (${metrics.date}).`}
                actions={
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => window.location.reload()}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                        >
                            <RefreshCw className="w-3.5 h-3.5 text-slate-500" />
                            Actualizar
                        </button>
                    </div>
                }
            />

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
                        label: 'vs. jornada anterior',
                    }}
                />

                <KpiCard
                    title="Cobranza Recaudada"
                    value={formattedCollected}
                    icon={CircleDollarSign}
                    variant="success"
                    subtitle={`Efectivo: $${kpis.cashPayments.toLocaleString('es-AR')} | Transf: $${kpis.transferPayments.toLocaleString('es-AR')}`}
                />

                <KpiCard
                    title="Paradas de Ruta"
                    value={`${kpis.completedStops} / ${kpis.totalStops}`}
                    icon={MapPin}
                    variant="accent"
                    trend={{
                        value: `${kpis.stopsProgressPercent}%`,
                        isPositive: true,
                        label: 'completadas hoy',
                    }}
                />

                <KpiCard
                    title="Movimiento Envases"
                    value={`+${kpis.containersDelivered} / -${kpis.containersReturned}`}
                    icon={Package}
                    variant="amber"
                    subtitle="Entregados vs. Retornados"
                />
            </div>

            {/* 2. Main Content Grid: Repartidores Status & Activity */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Repartidores En Ruta Table (2 Cols) */}
                <div className="lg:col-span-2 bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                    <div className="p-4 sm:px-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-bold font-display text-slate-900">
                                Estado de Repartidores en Calle
                            </h2>
                            <p className="text-xs text-slate-500">
                                Avance de entregas y ruteo en tiempo real
                            </p>
                        </div>
                        <span className="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                            {repartidores.length} choferes asignados
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3">Repartidor</th>
                                    <th className="px-6 py-3">Estado</th>
                                    <th className="px-6 py-3">Progreso de Paradas</th>
                                    <th className="px-6 py-3">Horario</th>
                                    <th className="px-6 py-3 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {repartidores.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-8 text-center text-slate-400">
                                            No hay rutas activas planificadas para hoy.
                                        </td>
                                    </tr>
                                ) : (
                                    repartidores.map((rep) => (
                                        <tr key={rep.id} className="hover:bg-slate-50/80 transition">
                                            <td className="px-6 py-4 font-semibold text-slate-900 flex items-center gap-2.5">
                                                <div className="w-7 h-7 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-[11px] font-bold text-slate-700">
                                                    {rep.name.charAt(0)}
                                                </div>
                                                <span>{rep.name}</span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <StatusBadge status={rep.status} />
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="w-36">
                                                    <div className="flex justify-between text-[11px] font-medium text-slate-600 mb-1">
                                                        <span>{rep.completedStops} de {rep.totalStops}</span>
                                                        <span>{rep.progressPercent}%</span>
                                                    </div>
                                                    <div className="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                        <div
                                                            className="h-full bg-blue-600 rounded-full transition-all duration-300"
                                                            style={{ width: `${rep.progressPercent}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-slate-500">
                                                {rep.startedAt ? (
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="w-3.5 h-3.5 text-slate-400" />
                                                        {rep.startedAt} hs
                                                    </span>
                                                ) : (
                                                    <span>-</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                <button
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-800 transition"
                                                >
                                                    Ver Ruta
                                                    <ArrowUpRight className="w-3.5 h-3.5" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Quick Actions & Summary (1 Col) */}
                <div className="space-y-6">
                    {/* Quick Access Card */}
                    <div className="bg-white rounded-lg border border-slate-200 p-5 shadow-2xs">
                        <h3 className="text-sm font-bold font-display text-slate-900 mb-3">
                            Accesos Directos
                        </h3>
                        <div className="grid grid-cols-2 gap-2 text-xs font-medium">
                            <button className="p-3 rounded-lg border border-slate-100 bg-slate-50 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700 text-slate-700 transition flex flex-col items-center justify-center gap-1.5 text-center">
                                <Truck className="w-5 h-5 text-blue-600" />
                                <span>Despachar Camión</span>
                            </button>
                            <button className="p-3 rounded-lg border border-slate-100 bg-slate-50 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700 text-slate-700 transition flex flex-col items-center justify-center gap-1.5 text-center">
                                <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                                <span>Cierre de Caja</span>
                            </button>
                        </div>
                    </div>

                    {/* Stock Alert / Summary */}
                    <div className="bg-blue-900 text-white rounded-lg p-5 shadow-xs relative overflow-hidden">
                        <div className="relative z-10">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-blue-300">
                                Ecosistema Marilin
                            </span>
                            <h4 className="text-base font-bold font-display mt-0.5">
                                Base Central Jujuy
                            </h4>
                            <p className="text-xs text-blue-100 mt-2 leading-relaxed">
                                Plataforma operativa de logística, cobranzas y envases retornables en tiempo real.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
