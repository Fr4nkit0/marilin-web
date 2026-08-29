import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, StatusBadge, KpiCard } from '@/Components/StitchUi';
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

interface RepartidorItem {
    id: number;
    name: string;
    email?: string;
    totalCustomers: number;
    currentRoute?: {
        id: number;
        status: string;
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
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('drivers.index'),
            { search: search || undefined },
            { preserveState: true, replace: true }
        );
    };

    const activeRoutesCount = repartidores.filter(
        (r) => r.currentRoute?.status === 'IN_PROGRESS' || r.currentRoute?.status === 'EN_RUTA'
    ).length;

    const completedRoutesCount = repartidores.filter(
        (r) => r.currentRoute?.status === 'COMPLETED' || r.currentRoute?.status === 'FINALIZADO'
    ).length;

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Flota & Choferes
                    </span>
                </div>
            }
        >
            <Head title="Choferes - Marilin" />

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

            {/* Search Filter */}
            <div className="bg-white rounded-lg border border-slate-200 p-4 mb-6 shadow-2xs">
                <form onSubmit={handleSearch} className="relative">
                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Buscar por nombre de chofer o email..."
                        className="w-full pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    />
                </form>
            </div>

            {/* Repartidores Table */}
            <div className="bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Chofer</th>
                                <th className="px-6 py-3.5">Estado Ruta Hoy</th>
                                <th className="px-6 py-3.5">Progreso de Entregas</th>
                                <th className="px-6 py-3.5 text-center">Clientes Asignados</th>
                                <th className="px-6 py-3.5">Horario Salida</th>
                                <th className="px-6 py-3.5 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {repartidores.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-8 text-center text-slate-400">
                                        No se encontraron choferes registrados.
                                    </td>
                                </tr>
                            ) : (
                                repartidores.map((rep) => {
                                    const hasRoute = !!rep.currentRoute;
                                    const status = rep.currentRoute?.status || 'SIN_ACTIVIDAD';
                                    const progress = rep.currentRoute?.progressPercent || 0;

                                    return (
                                        <tr key={rep.id} className="hover:bg-slate-50/80 transition">
                                            {/* Driver Name */}
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">
                                                        {rep.name.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold text-slate-900 text-sm">
                                                            {rep.name}
                                                        </p>
                                                        {rep.email && (
                                                            <p className="text-[11px] text-slate-400">
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
                                                    <div className="w-40">
                                                        <div className="flex justify-between text-[11px] font-medium text-slate-600 mb-1">
                                                            <span>
                                                                {rep.currentRoute?.completedStops} de {rep.currentRoute?.totalStops}
                                                            </span>
                                                            <span className="font-bold">{progress}%</span>
                                                        </div>
                                                        <div className="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                            <div
                                                                className="h-full bg-blue-600 rounded-full transition-all duration-300"
                                                                style={{ width: `${progress}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">Sin ruta hoy</span>
                                                )}
                                            </td>

                                            {/* Assigned Customers */}
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700 font-medium text-[11px]">
                                                    <Users className="w-3 h-3 text-slate-400" />
                                                    {rep.totalCustomers} clientes
                                                </span>
                                            </td>

                                            {/* Timings */}
                                            <td className="px-6 py-4 text-slate-500">
                                                {rep.currentRoute?.startedAt ? (
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="w-3.5 h-3.5 text-slate-400" />
                                                        <span>{rep.currentRoute.startedAt} hs</span>
                                                    </div>
                                                ) : (
                                                    <span>-</span>
                                                )}
                                            </td>

                                            {/* Action */}
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={route('drivers.show', rep.id)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-slate-200 text-slate-700 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 font-medium transition"
                                                >
                                                    Ver Detalle
                                                    <ArrowUpRight className="w-3.5 h-3.5" />
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
