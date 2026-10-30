import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { useListFilters } from '@/lib/useListFilters';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import { PageHeader } from '@/ui/PageHeader';
import {
    Search,
    Truck,
    MapPin,
    Users,
    ArrowUpRight,
    Clock,
    CheckCircle2,
    Package,
    Navigation,
} from 'lucide-react';
import type { RouteStatus } from './types';

interface RepartidorItem {
    id: number;
    name: string;
    email?: string;
    totalCustomers: number;
    currentRoute?: {
        id: number;
        status: RouteStatus;
        totalStops: number;
        completedStops: number;
        progressPercent: number;
        startedAt?: string;
        completedAt?: string;
    } | null;
}

interface Props {
    repartidores: RepartidorItem[];
    filters: {
        search?: string;
    };
}

export default function RepartidoresIndex({ repartidores, filters }: Props) {
    const filter = useListFilters('drivers.index', { search: filters.search || '' }, { search: '' });

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        filter.submit();
    };

    const activeRoutesCount = repartidores.filter(
        (r) => r.currentRoute?.status === 'IN_PROGRESS'
    ).length;

    const completedRoutesCount = repartidores.filter(
        (r) => r.currentRoute?.status === 'COMPLETED'
    ).length;

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink-muted">
                        Flota & Choferes
                    </span>
                </div>
            }
        >
            <Head title="Choferes" />

            <PageHeader
                title="Gestión de Choferes"
                description="Supervisión en tiempo real de choferes, rutas asignadas y avance de entregas."
            />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                <KpiCard
                    title="Total Choferes"
                    value={repartidores.length}
                    icon={Truck}
                    variant="primary"
                    subtitle="Registrados en el sistema"
                />
                <KpiCard
                    title="En Ruta Hoy"
                    value={activeRoutesCount}
                    icon={Navigation}
                    variant="accent"
                    subtitle="Entregando pedidos ahora"
                />
                <KpiCard
                    title="Rutas Finalizadas"
                    value={completedRoutesCount}
                    icon={CheckCircle2}
                    variant="success"
                    subtitle="Completaron la jornada"
                />
            </div>

            {/* Search Filter - Impeccable Cloud */}
            <div className="bg-canvas/80 backdrop-blur-md rounded-2xl p-4 mb-6 shadow-sm border border-hairline/60 ">
                <form onSubmit={handleSearch} className="relative">
                    <Search className="w-5 h-5 text-ink-subtle absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        value={filter.values.search}
                        onChange={(e) => filter.draft('search', e.target.value)}
                        placeholder="Buscar por nombre de chofer o email..."
                        className="w-full pl-10 pr-4 py-2.5 text-sm bg-canvas border border-hairline text-ink font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 placeholder-ink-subtle"
                    />
                </form>
            </div>

            {/* Repartidores Table - Soft Borders, Light Theme */}
            <div className="bg-canvas rounded-lg shadow-sm overflow-hidden border border-hairline/60">
                <div className="table-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-canvas/80 backdrop-blur-md border-b border-hairline text-ink-muted font-medium text-sm tracking-wide">
                            <tr>
                                <th className="px-6 py-4">Chofer</th>
                                <th className="px-6 py-4">Estado Ruta Hoy</th>
                                <th className="px-6 py-4">Progreso de Entregas</th>
                                <th className="px-6 py-4 text-center">Clientes Asignados</th>
                                <th className="px-6 py-4">Horario Salida</th>
                                <th className="px-6 py-4 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {repartidores.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-ink-muted font-medium">
                                        <span className="table-empty">No se encontraron choferes registrados.</span>
                                    </td>
                                </tr>
                            ) : (
                                repartidores.map((rep) => {
                                    const hasRoute = !!rep.currentRoute;
                                    const status = rep.currentRoute?.status || 'SIN_ACTIVIDAD';
                                    const progress = rep.currentRoute?.progressPercent || 0;

                                    return (
                                        <tr key={rep.id} className="hover:bg-canvas-soft transition-colors">
                                            {/* Driver Name */}
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-lg bg-surface-1 text-ink-secondary flex items-center justify-center font-semibold shadow-sm">
                                                        {rep.name.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold text-ink">
                                                            {rep.name}
                                                        </p>
                                                        {rep.email && (
                                                            <p className="text-xs text-ink-muted mt-0.5">
                                                                {rep.email}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>

                                            {/* Status Badge */}
                                            <td className="px-6 py-4">
                                                <StatusBadge status={status} />
                                            </td>

                                            {/* Progress */}
                                            <td className="px-6 py-4">
                                                {hasRoute ? (
                                                    <div className="w-48">
                                                        <div className="flex justify-between text-xs font-medium text-ink-secondary mb-1.5">
                                                            <span>
                                                                {rep.currentRoute?.completedStops} de {rep.currentRoute?.totalStops}
                                                            </span>
                                                            <span className="text-ink font-semibold">{progress}%</span>
                                                        </div>
                                                        <div className="w-full h-2 bg-surface-1 rounded-lg overflow-hidden">
                                                            <div
                                                                className="h-full bg-sky-600 transition-all duration-300 rounded-lg dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                                style={{ width: `${progress}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-ink-subtle font-medium text-xs">Sin ruta hoy</span>
                                                )}
                                            </td>

                                            {/* Assigned Customers */}
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-1 text-ink-secondary font-medium text-xs">
                                                    <Users className="w-4 h-4" />
                                                    {rep.totalCustomers} clientes
                                                </span>
                                            </td>

                                            {/* Timings */}
                                            <td className="px-6 py-4 font-medium text-ink-secondary">
                                                {rep.currentRoute?.startedAt ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <Clock className="w-4 h-4 text-ink-subtle" />
                                                        <span>{rep.currentRoute.startedAt} hs</span>
                                                    </div>
                                                ) : (
                                                    <span className="text-ink-subtle">-</span>
                                                )}
                                            </td>

                                            {/* Action */}
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={route('drivers.show', rep.id)}
                                                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-sky-600 text-white hover:bg-sky-700 font-medium text-sm transition-colors shadow-sm dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                >
                                                    Ver Detalle
                                                    <ArrowUpRight className="w-4 h-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}

