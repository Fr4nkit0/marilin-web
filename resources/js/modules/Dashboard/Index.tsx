import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import DashboardDetailModal from './components/DashboardDetailModal';
import { DashboardDetailKind, DashboardMetrics, DriverSyncStatus } from './types';
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
import { money } from '@/lib/format';

const REFRESH_EVERY_MS = 30_000;

function minutesAgo(since: number, now: number): string {
    const seconds = Math.max(0, Math.round((now - since) / 1000));
    if (seconds < 60) return 'hace unos segundos';
    const minutes = Math.round(seconds / 60);
    return minutes === 1 ? 'hace 1 minuto' : `hace ${minutes} minutos`;
}

function lastSyncLabel(status: DriverSyncStatus, now: number): string {
    if (!status.lastSyncAt) return 'Nunca sincronizó';
    return `Sincronizó ${minutesAgo(new Date(status.lastSyncAt).getTime(), now)}`;
}

interface Props {
    metrics: DashboardMetrics;
}

export default function Dashboard({ metrics }: Props) {
    const { kpis, repartidores } = metrics;
    const [openDetail, setOpenDetail] = useState<DashboardDetailKind | null>(null);
    const [updatedAt, setUpdatedAt] = useState(() => Date.now());
    const [now, setNow] = useState(() => Date.now());

    // Every refresh brings new metrics; that moment is what "Actualizado hace X" counts from.
    useEffect(() => setUpdatedAt(Date.now()), [metrics]);

    useEffect(() => {
        const refresh = setInterval(
            () => router.reload({ only: ['metrics'] }),
            REFRESH_EVERY_MS,
        );
        const tick = setInterval(() => setNow(Date.now()), 10_000);

        return () => {
            clearInterval(refresh);
            clearInterval(tick);
        };
    }, []);

    const showDay = (date: string) => {
        if (!date) return;
        router.get(route('dashboard'), { date }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCashClose = (driverId: number) => {
        router.visit(route('cash-close.index', { repartidor_id: driverId, date: metrics.date }));
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink">
                        Dashboard
                    </span>
                </div>
            }
        >
            <Head title="Panel de Control - Marilin" />

            {/* Page Header */}
            <div className="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-ink tracking-tight">
                        Dashboard
                    </h1>
                    <p className="text-sm text-ink-muted mt-1">
                        Resumen operativo del día: <span className="font-medium text-ink-secondary">{metrics.date}</span>
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="text-xs text-ink-subtle whitespace-nowrap">
                        Actualizado {minutesAgo(updatedAt, now)}
                    </span>
                    <input
                        type="date"
                        value={metrics.date}
                        onChange={(e) => showDay(e.target.value)}
                        aria-label="Día del resumen"
                        style={{ colorScheme: 'light' }}
                        className="py-2 px-3 text-sm font-medium border border-hairline/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent bg-canvas text-ink shadow-sm"
                    />
                    <button
                        onClick={() => router.reload({ only: ['metrics'] })}
                        className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-canvas text-sm font-medium text-ink-secondary hover:bg-canvas-soft transition-all shadow-[0_1px_2px_rgba(0,0,0,0.05)] ring-1 ring-inset ring-hairline/80 cursor-pointer"
                    >
                        <RefreshCw className="w-4 h-4 text-ink-subtle" strokeWidth={2} />
                        Actualizar
                    </button>
                </div>
            </div>

            {/* 1. KPIs Row */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <KpiCard
                    title="Ventas del Día"
                    onClick={() => setOpenDetail('sales')}
                    value={money(kpis.totalSales)}
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
                    onClick={() => setOpenDetail('payments')}
                    value={money(kpis.totalCollected)}
                    icon={CircleDollarSign}
                    variant="success"
                    subtitle={`EFE: ${money(kpis.cashPayments)} | TRF: ${money(kpis.transferPayments)}`}
                />

                <KpiCard
                    title="Paradas de Ruta"
                    onClick={() => setOpenDetail('stops')}
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
                    onClick={() => setOpenDetail('containers')}
                    value={`+${kpis.containersDelivered} / -${kpis.containersReturned}`}
                    icon={Package}
                    variant="slate"
                    subtitle="Entregados vs retornados"
                />
            </div>

            {/* 2. Main Content Grid: Repartidores & Actions */}
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                {/* Repartidores En Ruta Table */}
                <div className="lg:col-span-3 bg-canvas rounded-2xl border border-hairline/60 shadow-sm overflow-hidden glass">
                    <div className="px-6 py-5 border-b border-hairline flex items-center justify-between">
                        <h2 className="text-base font-semibold text-ink">
                            Repartidores en ruta
                        </h2>
                        <span className="px-3 py-1 rounded-full bg-surface-1 text-ink-secondary text-xs font-medium">
                            {repartidores.length} activos
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="bg-canvas-soft/50 border-b border-hairline">
                                <tr>
                                    <th className="px-6 py-3 text-xs font-medium text-ink-muted w-1/4">
                                        Repartidor
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-ink-muted w-1/4">
                                        Progreso
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-ink-muted w-1/4">
                                        Estado
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-ink-muted text-right">
                                        Vendido
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-ink-muted text-right">
                                        Cobrado
                                    </th>
                                    <th className="px-6 py-3 text-xs font-medium text-ink-muted w-1/4 text-right">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-canvas divide-y divide-hairline">
                                {repartidores.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-6 py-8 text-center text-sm font-medium text-ink-subtle">
                                            No hay repartidores activos en este momento.
                                        </td>
                                    </tr>
                                ) : (
                                    repartidores.map((rep) => (
                                        <tr
                                            key={rep.id}
                                            onClick={() => openCashClose(rep.id)}
                                            className="hover:bg-canvas-soft transition-colors cursor-pointer"
                                            title="Ver cierre de caja del día"
                                        >
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 bg-surface-1 text-ink-secondary flex items-center justify-center font-semibold text-xs rounded-full">
                                                        {rep.name.substring(0,2).toUpperCase()}
                                                    </div>
                                                    <span className="font-medium text-ink">{rep.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-ink">{rep.completedStops} / {rep.totalStops}</span>
                                                        <span className="text-xs text-ink-muted">Paradas</span>
                                                    </div>
                                                    <div className="w-24 h-2 bg-surface-1 rounded-full overflow-hidden">
                                                        <div
                                                            className="h-full bg-sky-600 transition-all duration-300 rounded-full dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                            style={{ width: `${rep.progressPercent}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <StatusBadge status={rep.status} />
                                                    {rep.startedAt && (
                                                        <span className="text-xs text-ink-muted">
                                                            desde {rep.startedAt}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-right font-medium text-ink">{money(rep.totalSales)}</td>
                                            <td className="px-6 py-4 text-right font-medium text-ink">{money(rep.totalCollected)}</td>
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={route('cash-close.index', { repartidor_id: rep.id, date: metrics.date })}
                                                    onClick={(e) => e.stopPropagation()}
                                                    aria-label={`Cierre de caja de ${rep.name}`}
                                                    className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-canvas border border-hairline/60 hover:bg-canvas-soft text-ink-secondary transition-colors shadow-sm cursor-pointer"
                                                >
                                                    <ArrowRight className="w-4 h-4" strokeWidth={2} />
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                                <tr title="Ventas y cobros hechos en la casa, sin repartidor">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 bg-surface-1 text-ink-secondary flex items-center justify-center font-semibold text-xs rounded-full">
                                                {metrics.casa.name.substring(0, 2).toUpperCase()}
                                            </div>
                                            <span className="font-medium text-ink">{metrics.casa.name}</span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-xs text-ink-muted">Sin ruta</td>
                                    <td className="px-6 py-4"></td>
                                    <td className="px-6 py-4 text-right font-medium text-ink">{money(metrics.casa.totalSales)}</td>
                                    <td className="px-6 py-4 text-right font-medium text-ink">{money(metrics.casa.totalCollected)}</td>
                                    <td className="px-6 py-4"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Quick Actions (1 Col) */}
                <div className="space-y-6">
                    <div className="bg-canvas rounded-2xl border border-hairline/60 p-6 shadow-sm glass">
                        <h3 className="text-sm font-semibold text-ink mb-4">
                            Acciones rápidas
                        </h3>
                        <div className="flex flex-col gap-3">
                            <Link
                                href={route('routes.index')}
                                className="w-full flex items-center justify-between px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white transition-all rounded-lg text-sm font-medium shadow-[0_1px_2px_rgba(0,0,0,0.1),inset_0_1px_0_rgba(255,255,255,0.2)] ring-1 ring-inset ring-sky-700/50 cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                            >
                                <span className="flex items-center gap-2">
                                    <Truck className="w-4 h-4 text-ink-subtle" strokeWidth={2} />
                                    Asignar ruta
                                </span>
                                <ArrowRight className="w-4 h-4 text-ink-subtle" strokeWidth={2} />
                            </Link>
                            <Link
                                href={route('cash-close.index', { date: metrics.date })}
                                className="w-full flex items-center justify-between px-4 py-2.5 bg-canvas hover:bg-canvas-soft text-ink-secondary transition-all rounded-lg text-sm font-medium shadow-[0_1px_2px_rgba(0,0,0,0.05)] ring-1 ring-inset ring-hairline/80 cursor-pointer"
                            >
                                <span className="flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-ink-subtle" strokeWidth={2} />
                                    Cierre de caja
                                </span>
                                <ArrowRight className="w-4 h-4 text-ink-subtle" strokeWidth={2} />
                            </Link>
                        </div>
                    </div>

                    {/* App sync status per driver */}
                    <div className="bg-canvas rounded-2xl border border-hairline/60 p-6 shadow-sm glass">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-sm font-semibold text-ink">Sistema</span>
                            <div
                                className={`w-2.5 h-2.5 rounded-full ${
                                    metrics.sync.some((status) => status.stale) ? 'bg-amber-500' : 'bg-emerald-500'
                                }`}
                            ></div>
                        </div>
                        {metrics.sync.length === 0 ? (
                            <p className="text-xs text-ink-muted">No hay repartidores habilitados.</p>
                        ) : (
                            <ul className="space-y-2.5">
                                {metrics.sync.map((status) => (
                                    <li key={status.id} className="flex items-start justify-between gap-3 text-xs">
                                        <span className="font-medium text-ink-secondary">{status.name}</span>
                                        <span
                                            className={`text-right ${status.stale ? 'text-amber-700 dark:text-amber-300 font-medium' : 'text-ink-muted'}`}
                                        >
                                            {lastSyncLabel(status, now)}
                                            {status.stale && <span className="block">En ruta sin sincronizar</span>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

            </div>

            <DashboardDetailModal kind={openDetail} details={metrics.details} onClose={() => setOpenDetail(null)} />
        </AdminLayout>
    );
}

