import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useListFilters } from '@/lib/useListFilters';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import { PageHeader } from '@/ui/PageHeader';
import RouteStockLoadModal, { LoadableProduct } from '@/modules/Street/RouteStock/components/RouteStockLoadModal';
import {
    CalendarCheck,
    Truck,
    MapPin,
    ArrowUpRight,
    Clock,
    CheckCircle2,
    Package,
    Navigation,
    Calendar,
    ChevronDown,
    ChevronUp,
    Filter,
    PackagePlus,
} from 'lucide-react';
import type { RouteStatus, RouteStopRow } from './types';

interface StopItem extends RouteStopRow {
    plannedSequence: number;
    outcome?: string;
}

interface RouteStockItem {
    productId: number;
    productName: string;
    loaded: number;
    current: number;
}

interface LoadHistoryItem {
    id: number;
    isInitial: boolean;
    loadedAt: string | null;
    productName: string;
    quantity: number;
    loadedBy: string | null;
}

interface StockItem {
    id: number;
    productName: string;
    currentQuantity: number;
}

interface RouteItem {
    id: number;
    status: RouteStatus;
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
    routeStock: RouteStockItem[];
    loadHistory: LoadHistoryItem[];
    canLoad: boolean;
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
    products: LoadableProduct[];
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
    products,
    selectedDate,
    filters,
    kpis,
}: Props) {
    const [loadingRoute, setLoadingRoute] = useState<RouteItem | null>(null);
    const filter = useListFilters(
        'routes.index',
        { date: selectedDate, status: filters.status || 'all', driver_id: filters.driver_id || '' },
        { date: '', status: 'all', driver_id: '' },
    );
    const [expandedRouteId, setExpandedRouteId] = useState<number | null>(
        routes.length > 0 ? routes[0].id : null
    );

    const toggleRouteExpand = (id: number) => {
        setExpandedRouteId(expandedRouteId === id ? null : id);
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-slate-500">
                        Logística & Despacho
                    </span>
                </div>
            }
        >
            <Head title="Despacho y Rutas - Marilin" />

            <PageHeader
                title="Despacho Diario y Gestión de Rutas"
                description={`Supervisión de paradas, inventario de ruta y avance de recorridos para la fecha seleccionada.`}
            />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <KpiCard
                    title="Rutas Asignadas"
                    value={kpis.totalRoutes}
                    icon={Truck}
                    variant="primary"
                    subtitle="Rutas en jornada"
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

            {/* Filters Bar - Premium SaaS Style */}
            <div className="bg-white border border-gray-200/60 rounded-2xl p-4 mb-6 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        {/* Date Picker */}
                        <div className="flex items-center gap-2 bg-white border border-gray-200/60 rounded-lg px-3 py-2 text-sm text-slate-900 focus-within:ring-2 focus-within:ring-indigo-600 focus-within:border-transparent transition-all shadow-sm">
                            <Calendar className="w-4 h-4 text-slate-400" />
                            <input
                                type="date"
                                value={filter.values.date}
                                onChange={(e) => filter.set('date', e.target.value)}
                                style={{ colorScheme: 'light' }}
                                className="bg-transparent border-none p-0 text-sm font-medium focus:ring-0 focus:outline-none cursor-pointer text-slate-900"
                            />
                        </div>

                        {/* Status Filter */}
                        <div className="flex items-center gap-2">
                            <select
                                value={filter.values.status}
                                onChange={(e) => filter.set('status', e.target.value)}
                                className="text-sm font-medium border border-gray-200/60 rounded-lg py-2 pl-3 pr-8 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition-all shadow-sm"
                            >
                                <option value="all">Todos los Estados</option>
                                <option value="IN_PROGRESS">En Curso</option>
                                <option value="PLANNED">Planificadas</option>
                                <option value="COMPLETED">Completadas</option>
                            </select>
                        </div>

                        {/* Driver Filter */}
                        <select
                            value={filter.values.driver_id}
                            onChange={(e) => filter.set('driver_id', e.target.value)}
                            className="text-sm font-medium border border-gray-200/60 rounded-lg py-2 pl-3 pr-8 bg-white text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:border-transparent transition-all shadow-sm"
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
                        // No date: the server answers with today in its own time zone.
                        onClick={() => filter.setMany({ date: '', status: 'all', driver_id: '' })}
                        className="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            {/* Routes List */}
            {routes.length === 0 ? (
                <div className="bg-white border border-gray-200/60 rounded-2xl p-12 text-center shadow-sm">
                    <Truck className="w-12 h-12 text-slate-300 mx-auto mb-4" />
                    <h3 className="text-lg font-semibold text-slate-900">No hay rutas asignadas para esta fecha</h3>
                    <p className="text-sm text-slate-500 mt-2">
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
                                className="bg-white border border-gray-200/60 rounded-2xl shadow-sm overflow-hidden transition-all duration-200"
                            >
                                {/* Route Summary Header Card */}
                                <div
                                    onClick={() => toggleRouteExpand(routeItem.id)}
                                    className="p-5 flex flex-wrap items-center justify-between gap-4 cursor-pointer hover:bg-slate-50/50 transition-colors"
                                >
                                    {/* Left: Driver & Status */}
                                    <div className="flex items-center gap-4">
                                        <div className="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-medium text-lg shadow-sm">
                                            {routeItem.driver.name.charAt(0)}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <h3 className="text-base font-semibold text-slate-900">
                                                    {routeItem.driver.name}
                                                </h3>
                                                <StatusBadge status={routeItem.status} />
                                            </div>
                                            <div className="flex items-center gap-3 text-sm text-slate-500 mt-1">
                                                <span className="font-medium">Ruta #{routeItem.id}</span>
                                                <span className="text-slate-300">•</span>
                                                {routeItem.startedAt ? (
                                                    <span className="flex items-center gap-1.5 text-slate-600">
                                                        <Clock className="w-3.5 h-3.5" />
                                                        Salida: {routeItem.startedAt} hs
                                                    </span>
                                                ) : (
                                                    <span>Sin iniciar</span>
                                                )}
                                                {routeItem.completedAt && (
                                                    <>
                                                        <span className="text-slate-300">•</span>
                                                        <span className="text-slate-600">Llegada: {routeItem.completedAt} hs</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Middle: Stops Progress */}
                                    <div className="w-full sm:w-64">
                                        <div className="flex justify-between text-xs font-medium text-slate-500 mb-2">
                                            <span>Paradas completadas</span>
                                            <span className="text-slate-900 font-semibold">
                                                {routeItem.stats.completedStops} / {routeItem.stats.totalStops} (
                                                {routeItem.stats.progressPercent}%)
                                            </span>
                                        </div>
                                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div
                                                className={`h-full rounded-full transition-all duration-300 ${
                                                    routeItem.stats.progressPercent === 100
                                                        ? 'bg-slate-900'
                                                        : 'bg-slate-900'
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
                                                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white border border-gray-200/60 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
                                            >
                                                Ver ficha
                                                <ArrowUpRight className="w-4 h-4" />
                                            </Link>
                                        )}
                                        <button className="p-2 rounded-full text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition-colors">
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
                                    <div className="bg-slate-50/50 p-6 space-y-8 border-t border-gray-100">
                                        {/* Truck Inventory Section */}
                                        <div>
                                            <h4 className="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                                                <Package className="w-4 h-4 text-slate-500" />
                                                Inventario de ruta
                                                {routeItem.canLoad && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setLoadingRoute(routeItem)}
                                                        className="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-medium shadow-sm cursor-pointer"
                                                    >
                                                        <PackagePlus className="w-3.5 h-3.5" />
                                                        {routeItem.loadHistory.length === 0 ? 'Registrar carga inicial' : 'Registrar recarga'}
                                                    </button>
                                                )}
                                            </h4>
                                            {routeItem.routeStock.length === 0 ? (
                                                <p className="text-sm text-slate-500 mb-4">
                                                    Todavía no se registró ninguna carga para esta ruta.
                                                </p>
                                            ) : (
                                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                                                    {routeItem.routeStock.map((stock) => (
                                                        <div
                                                            key={stock.productId}
                                                            className="bg-white border border-gray-200/60 rounded-xl p-4 flex items-center justify-between shadow-sm"
                                                        >
                                                            <div>
                                                                <p className="text-sm font-medium text-slate-900">
                                                                    {stock.productName}
                                                                </p>
                                                                <p className="text-xs text-slate-500 mt-1">
                                                                    Cargado en el día: {stock.loaded} u.
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <span className="text-xs text-slate-500">Remanente</span>
                                                                <p className="text-lg font-semibold text-slate-900">
                                                                    {stock.current} u.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            {routeItem.loadHistory.length > 0 && (
                                                <div className="bg-white border border-gray-200/60 rounded-xl shadow-sm overflow-hidden">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="border-b border-gray-100 text-slate-500">
                                                            <tr>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Hora</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Carga</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Producto</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Registró</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium text-right">Unidades</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-gray-50">
                                                            {routeItem.loadHistory.map((load) => (
                                                                <tr key={load.id}>
                                                                    <td className="px-4 py-2.5 text-slate-700 whitespace-nowrap">
                                                                        {load.loadedAt ?? '—'}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-slate-700">
                                                                        {load.isInitial ? 'Inicial' : 'Recarga'}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 font-medium text-slate-900">
                                                                        {load.productName}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-slate-600">{load.loadedBy ?? '—'}</td>
                                                                    <td className="px-4 py-2.5 text-right font-semibold text-slate-900">
                                                                        +{load.quantity}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>

                                        {/* Stops Table */}
                                        <div>
                                            <h4 className="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2">
                                                <MapPin className="w-4 h-4 text-slate-500" />
                                                Hoja de Ruta y Paradas ({routeItem.stops.length})
                                            </h4>

                                            <div className="bg-white border border-gray-200/60 rounded-xl overflow-hidden shadow-sm">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="bg-slate-50/80 backdrop-blur-md text-slate-500 font-medium text-xs border-b border-gray-200/60">
                                                        <tr>
                                                            <th className="px-6 py-4 text-center w-16">#</th>
                                                            <th className="px-6 py-4">Cliente</th>
                                                            <th className="px-6 py-4">Dirección</th>
                                                            <th className="px-6 py-4">Estado Visita</th>
                                                            <th className="px-6 py-4">Hora</th>
                                                            <th className="px-6 py-4">Notas / Resultado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {routeItem.stops.length === 0 ? (
                                                            <tr>
                                                                <td
                                                                    colSpan={6}
                                                                    className="px-6 py-8 text-center text-slate-500"
                                                                >
                                                                    No hay paradas registradas en esta ruta.
                                                                </td>
                                                            </tr>
                                                        ) : (
                                                            routeItem.stops.map((stop, idx) => (
                                                                <tr
                                                                    key={stop.id}
                                                                    className="border-b border-gray-50 last:border-0 hover:bg-slate-50/50 transition-colors"
                                                                >
                                                                    <td className="px-6 py-4 text-center">
                                                                        <span className="inline-flex w-7 h-7 rounded-full bg-slate-100 text-slate-700 items-center justify-center text-xs font-medium">
                                                                            {idx + 1}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-6 py-4">
                                                                        <span className="font-medium text-slate-900 block">
                                                                            {stop.customer.name}
                                                                        </span>
                                                                        {stop.customer.phone && (
                                                                            <span className="text-xs text-slate-500 mt-0.5 block">
                                                                                {stop.customer.phone}
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-slate-500">
                                                                        {stop.customer.address}
                                                                    </td>
                                                                    <td className="px-6 py-4">
                                                                        <StatusBadge status={stop.status} />
                                                                    </td>
                                                                    <td className="px-6 py-4 text-slate-500">
                                                                        {stop.arrivedAt ? `${stop.arrivedAt} hs` : '-'}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-slate-500">
                                                                        {stop.outcome ? (
                                                                            <span className="font-medium text-slate-900 text-sm">
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
            <RouteStockLoadModal
                routeId={loadingRoute?.id ?? null}
                driverName={loadingRoute?.driver.name ?? ''}
                isFirstLoad={(loadingRoute?.loadHistory.length ?? 0) === 0}
                products={products}
                onClose={() => setLoadingRoute(null)}
            />
        </AdminLayout>
    );
}
