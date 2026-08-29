import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, StatusBadge, KpiCard } from '@/Components/StitchUi';
import {
    CalendarCheck,
    Truck,
    MapPin,
    Users,
    ArrowUpRight,
    Clock,
    CheckCircle2,
    Package,
    Navigation,
    Calendar,
    ChevronDown,
    ChevronUp,
    Phone,
    Filter,
} from 'lucide-react';

interface StopItem {
    id: number;
    plannedSequence: number;
    customer: {
        id: number;
        name: string;
        address: string;
        phone?: string;
    };
    status: string;
    arrivedAt?: string;
    outcome?: string;
    notes?: string;
}

interface LoadItem {
    id: number;
    productName: string;
    initialQuantity: number;
}

interface StockItem {
    id: number;
    productName: string;
    currentQuantity: number;
}

interface RouteItem {
    id: number;
    status: string;
    date: string;
    rawDate: string;
    startedAt?: string;
    completedAt?: string;
    driver: {
        id?: number;
        name: string;
        phone?: string;
    };
    stats: {
        totalStops: number;
        completedStops: number;
        pendingStops: number;
        progressPercent: number;
    };
    truckLoads: LoadItem[];
    currentStock: StockItem[];
    stops: StopItem[];
}

interface DriverOption {
    id: number;
    name: string;
}

interface Props {
    routes: RouteItem[];
    drivers: DriverOption[];
    selectedDate: string;
    filters: {
        status: string;
        driver_id?: string;
    };
    kpis: {
        totalRoutes: number;
        inProgressRoutes: number;
        completedRoutes: number;
        totalPlannedStops: number;
        totalCompletedStops: number;
        globalProgress: number;
    };
}

export default function RoutesIndex({
    routes,
    drivers,
    selectedDate,
    filters,
    kpis,
}: Props) {
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [driverFilter, setDriverFilter] = useState(filters.driver_id || '');
    const [date, setDate] = useState(selectedDate);
    const [expandedRouteId, setExpandedRouteId] = useState<number | null>(
        routes.length > 0 ? routes[0].id : null
    );

    const applyFilters = (newDate?: string, newStatus?: string, newDriver?: string) => {
        router.get(
            route('routes.index'),
            {
                date: newDate !== undefined ? newDate : date,
                status: newStatus !== undefined ? newStatus : statusFilter,
                driver_id: newDriver !== undefined ? newDriver || undefined : driverFilter || undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const toggleRouteExpand = (id: number) => {
        setExpandedRouteId(expandedRouteId === id ? null : id);
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Logística & Despacho
                    </span>
                </div>
            }
        >
            <Head title="Despacho y Rutas - Marilin" />

            <PageHeader
                title="Despacho Diario y Gestión de Rutas"
                description={`Supervisión de paradas, stock en camión y avance de recorridos para la fecha seleccionada.`}
            />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <KpiCard
                    title="Rutas Asignadas"
                    value={kpis.totalRoutes}
                    icon={Truck}
                    variant="primary"
                    subtitle="Camiones en jornada"
                />
                <KpiCard
                    title="Rutas en Curso"
                    value={kpis.inProgressRoutes}
                    icon={Navigation}
                    variant="accent"
                    subtitle="Entregando pedidos ahora"
                />
                <KpiCard
                    title="Paradas Completadas"
                    value={`${kpis.totalCompletedStops} / ${kpis.totalPlannedStops}`}
                    icon={CheckCircle2}
                    variant="success"
                    subtitle={`${kpis.globalProgress}% avance general`}
                />
                <KpiCard
                    title="Rutas Finalizadas"
                    value={kpis.completedRoutes}
                    icon={CalendarCheck}
                    variant="slate"
                    subtitle="Completaron el recorrido"
                />
            </div>

            {/* Filters Bar */}
            <div className="bg-white rounded-xl border border-slate-200 p-4 mb-6 shadow-2xs">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        {/* Date Picker */}
                        <div className="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 text-xs text-slate-700">
                            <Calendar className="w-3.5 h-3.5 text-slate-400" />
                            <input
                                type="date"
                                value={date}
                                onChange={(e) => {
                                    setDate(e.target.value);
                                    applyFilters(e.target.value, statusFilter, driverFilter);
                                }}
                                className="bg-transparent border-none p-0 text-xs font-semibold focus:ring-0 focus:outline-none cursor-pointer"
                            />
                        </div>

                        {/* Status Filter */}
                        <div className="flex items-center gap-1.5">
                            <Filter className="w-3.5 h-3.5 text-slate-400 ml-1" />
                            <select
                                value={statusFilter}
                                onChange={(e) => {
                                    setStatusFilter(e.target.value);
                                    applyFilters(date, e.target.value, driverFilter);
                                }}
                                className="text-xs border border-slate-200 rounded-lg py-1.5 px-3 bg-white text-slate-700 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-medium"
                            >
                                <option value="all">Todos los Estados</option>
                                <option value="IN_PROGRESS">En Curso</option>
                                <option value="PLANNED">Planificadas</option>
                                <option value="COMPLETED">Completadas</option>
                            </select>
                        </div>

                        {/* Driver Filter */}
                        <select
                            value={driverFilter}
                            onChange={(e) => {
                                setDriverFilter(e.target.value);
                                applyFilters(date, statusFilter, e.target.value);
                            }}
                            className="text-xs border border-slate-200 rounded-lg py-1.5 px-3 bg-white text-slate-700 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-medium"
                        >
                            <option value="">Todos los Choferes</option>
                            {drivers.map((drv) => (
                                <option key={drv.id} value={drv.id}>
                                    {drv.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <button
                        onClick={() => {
                            setDate(new Date().toISOString().split('T')[0]);
                            setStatusFilter('all');
                            setDriverFilter('');
                            applyFilters(new Date().toISOString().split('T')[0], 'all', '');
                        }}
                        className="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            {/* Routes List */}
            {routes.length === 0 ? (
                <div className="bg-white rounded-xl border border-slate-200 p-12 text-center shadow-2xs">
                    <Truck className="w-10 h-10 text-slate-300 mx-auto mb-3" />
                    <h3 className="text-sm font-bold text-slate-800">No hay rutas asignadas para esta fecha</h3>
                    <p className="text-xs text-slate-500 mt-1">
                        Probá seleccionando otra fecha o cambiando los filtros de chofer/estado.
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    {routes.map((routeItem) => {
                        const isExpanded = expandedRouteId === routeItem.id;

                        return (
                            <div
                                key={routeItem.id}
                                className="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden transition-all duration-200"
                            >
                                {/* Route Summary Header Card */}
                                <div
                                    onClick={() => toggleRouteExpand(routeItem.id)}
                                    className="p-5 flex flex-wrap items-center justify-between gap-4 cursor-pointer hover:bg-slate-50/70 transition"
                                >
                                    {/* Left: Driver & Status */}
                                    <div className="flex items-center gap-3.5">
                                        <div className="w-10 h-10 rounded-full bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                                            {routeItem.driver.name.charAt(0)}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h3 className="text-sm font-bold text-slate-900">
                                                    {routeItem.driver.name}
                                                </h3>
                                                <StatusBadge status={routeItem.status} />
                                            </div>
                                            <div className="flex items-center gap-3 text-[11px] text-slate-500 mt-0.5">
                                                <span>Ruta #{routeItem.id}</span>
                                                <span>•</span>
                                                {routeItem.startedAt ? (
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="w-3 h-3 text-slate-400" />
                                                        Salida: {routeItem.startedAt} hs
                                                    </span>
                                                ) : (
                                                    <span>Sin iniciar</span>
                                                )}
                                                {routeItem.completedAt && (
                                                    <>
                                                        <span>•</span>
                                                        <span>Llegada: {routeItem.completedAt} hs</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Middle: Stops Progress */}
                                    <div className="w-full sm:w-48">
                                        <div className="flex justify-between text-[11px] font-medium text-slate-600 mb-1">
                                            <span>Paradas completadas</span>
                                            <span className="font-bold">
                                                {routeItem.stats.completedStops} de {routeItem.stats.totalStops} (
                                                {routeItem.stats.progressPercent}%)
                                            </span>
                                        </div>
                                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div
                                                className={`h-full rounded-full transition-all duration-300 ${
                                                    routeItem.stats.progressPercent === 100
                                                        ? 'bg-emerald-500'
                                                        : 'bg-blue-600'
                                                }`}
                                                style={{ width: `${routeItem.stats.progressPercent}%` }}
                                            />
                                        </div>
                                    </div>

                                    {/* Right: Action & Toggle */}
                                    <div className="flex items-center gap-3">
                                        {routeItem.driver.id && (
                                            <Link
                                                href={route('drivers.show', routeItem.driver.id)}
                                                onClick={(e) => e.stopPropagation()}
                                                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 transition"
                                            >
                                                Ver Ficha Chofer
                                                <ArrowUpRight className="w-3.5 h-3.5" />
                                            </Link>
                                        )}
                                        <button className="p-1 rounded-md text-slate-400 hover:text-slate-700">
                                            {isExpanded ? (
                                                <ChevronUp className="w-5 h-5" />
                                            ) : (
                                                <ChevronDown className="w-5 h-5" />
                                            )}
                                        </button>
                                    </div>
                                </div>

                                {/* Expanded Details: Truck Inventory & Stops Timeline */}
                                {isExpanded && (
                                    <div className="border-t border-slate-100 bg-slate-50/50 p-5 space-y-6">
                                        {/* Truck Inventory Section */}
                                        <div>
                                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                                                <Package className="w-3.5 h-3.5 text-slate-400" />
                                                Carga Física en Camión
                                            </h4>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                                {routeItem.truckLoads.map((load) => {
                                                    const currentInv = routeItem.currentStock.find(
                                                        (i) => i.productName === load.productName
                                                    );
                                                    return (
                                                        <div
                                                            key={load.id}
                                                            className="bg-white rounded-lg border border-slate-200 p-3 shadow-2xs flex items-center justify-between"
                                                        >
                                                            <div>
                                                                <p className="text-xs font-semibold text-slate-800">
                                                                    {load.productName}
                                                                </p>
                                                                <p className="text-[11px] text-slate-400">
                                                                    Carga inicial: {load.initialQuantity} u.
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <span className="text-[10px] uppercase font-bold text-slate-400">
                                                                    Remanente
                                                                </span>
                                                                <p className="text-sm font-bold text-blue-600">
                                                                    {currentInv ? currentInv.currentQuantity : 0} u.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        {/* Stops Table */}
                                        <div>
                                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
                                                <MapPin className="w-3.5 h-3.5 text-slate-400" />
                                                Hoja de Ruta y Paradas ({routeItem.stops.length})
                                            </h4>

                                            <div className="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-2xs">
                                                <table className="w-full text-left text-xs">
                                                    <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                                        <tr>
                                                            <th className="px-4 py-2.5 text-center w-12">#</th>
                                                            <th className="px-4 py-2.5">Cliente</th>
                                                            <th className="px-4 py-2.5">Dirección</th>
                                                            <th className="px-4 py-2.5">Estado Visita</th>
                                                            <th className="px-4 py-2.5">Hora</th>
                                                            <th className="px-4 py-2.5">Notas / Resultado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        {routeItem.stops.length === 0 ? (
                                                            <tr>
                                                                <td
                                                                    colSpan={6}
                                                                    className="px-4 py-6 text-center text-slate-400"
                                                                >
                                                                    No hay paradas registradas en esta ruta.
                                                                </td>
                                                            </tr>
                                                        ) : (
                                                            routeItem.stops.map((stop, idx) => (
                                                                <tr
                                                                    key={stop.id}
                                                                    className="hover:bg-slate-50/80 transition"
                                                                >
                                                                    <td className="px-4 py-3 text-center font-bold text-slate-400">
                                                                        {idx + 1}
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <span className="font-semibold text-slate-900">
                                                                            {stop.customer.name}
                                                                        </span>
                                                                        {stop.customer.phone && (
                                                                            <span className="block text-[11px] text-slate-400">
                                                                                {stop.customer.phone}
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-4 py-3 text-slate-600">
                                                                        {stop.customer.address}
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <StatusBadge status={stop.status} />
                                                                    </td>
                                                                    <td className="px-4 py-3 text-slate-500">
                                                                        {stop.arrivedAt ? `${stop.arrivedAt} hs` : '-'}
                                                                    </td>
                                                                    <td className="px-4 py-3 text-slate-500">
                                                                        {stop.outcome ? (
                                                                            <span className="font-medium text-slate-700">
                                                                                {stop.outcome}
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-slate-400 italic">
                                                                                Sin novedades
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ))
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </AdminLayout>
    );
}
