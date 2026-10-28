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
                    <span className="text-sm font-medium text-ink-muted">
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
            <div className="bg-canvas border border-hairline/60 rounded-2xl p-4 mb-6 shadow-sm glass">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        {/* Date Picker */}
                        <div className="flex items-center gap-2 bg-canvas border border-hairline/60 rounded-lg px-3 py-2 text-sm text-ink focus-within:ring-2 focus-within:ring-sky-600 focus-within:border-transparent transition-all shadow-sm">
                            <Calendar className="w-4 h-4 text-ink-subtle" />
                            <input
                                type="date"
                                value={filter.values.date}
                                onChange={(e) => filter.set('date', e.target.value)}
                                style={{ colorScheme: 'light' }}
                                className="bg-transparent border-none p-0 text-sm font-medium focus:ring-0 focus:outline-none cursor-pointer text-ink"
                            />
                        </div>

                        {/* Status Filter */}
                        <div className="flex items-center gap-2">
                            <select
                                value={filter.values.status}
                                onChange={(e) => filter.set('status', e.target.value)}
                                className="text-sm font-medium border border-hairline/60 rounded-lg py-2 pl-3 pr-8 bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent transition-all shadow-sm"
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
                            className="text-sm font-medium border border-hairline/60 rounded-lg py-2 pl-3 pr-8 bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent transition-all shadow-sm"
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
                        className="text-sm font-medium text-ink-muted hover:text-ink transition-colors"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            {/* Routes List */}
            {routes.length === 0 ? (
                <div className="bg-canvas border border-hairline/60 rounded-2xl p-12 text-center shadow-sm glass">
                    <Truck className="w-12 h-12 text-ink-subtle mx-auto mb-4" />
                    <h3 className="text-lg font-semibold text-ink">No hay rutas asignadas para esta fecha</h3>
                    <p className="text-sm text-ink-muted mt-2">
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
                                className="bg-canvas border border-hairline/60 rounded-2xl shadow-sm overflow-hidden transition-all duration-200 glass"
                            >
                                {/* Route Summary Header Card */}
                                <div
                                    onClick={() => toggleRouteExpand(routeItem.id)}
                                    className="p-5 flex flex-wrap items-center justify-between gap-4 cursor-pointer hover:bg-canvas-soft/50 transition-colors"
                                >
                                    {/* Left: Driver & Status */}
                                    <div className="flex items-center gap-4">
                                        <div className="w-10 h-10 rounded-full bg-sky-600 text-white flex items-center justify-center font-medium text-lg shadow-sm dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400">
                                            {routeItem.driver.name.charAt(0)}
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <h3 className="text-base font-semibold text-ink">
                                                    {routeItem.driver.name}
                                                </h3>
                                                <StatusBadge status={routeItem.status} />
                                            </div>
                                            <div className="flex items-center gap-3 text-sm text-ink-muted mt-1">
                                                <span className="font-medium">Ruta #{routeItem.id}</span>
                                                <span className="text-ink-subtle">•</span>
                                                {routeItem.startedAt ? (
                                                    <span className="flex items-center gap-1.5 text-ink-secondary">
                                                        <Clock className="w-3.5 h-3.5" />
                                                        Salida: {routeItem.startedAt} hs
                                                    </span>
                                                ) : (
                                                    <span>Sin iniciar</span>
                                                )}
                                                {routeItem.completedAt && (
                                                    <>
                                                        <span className="text-ink-subtle">•</span>
                                                        <span className="text-ink-secondary">Llegada: {routeItem.completedAt} hs</span>
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Middle: Stops Progress */}
                                    <div className="w-full sm:w-64">
                                        <div className="flex justify-between text-xs font-medium text-ink-muted mb-2">
                                            <span>Paradas completadas</span>
                                            <span className="text-ink font-semibold">
                                                {routeItem.stats.completedStops} / {routeItem.stats.totalStops} (
                                                {routeItem.stats.progressPercent}%)
                                            </span>
                                        </div>
                                        <div className="w-full h-2 bg-surface-1 rounded-full overflow-hidden">
                                            <div
                                                className={`h-full rounded-full transition-all duration-300 ${
                                                    routeItem.stats.progressPercent === 100
                                                        ? 'bg-sky-600 dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400'
                                                        : 'bg-sky-600 dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400'
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
                                                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-canvas border border-hairline/60 text-sm font-medium text-ink-secondary hover:bg-canvas-soft transition-colors shadow-sm"
                                            >
                                                Ver ficha
                                                <ArrowUpRight className="w-4 h-4" />
                                            </Link>
                                        )}
                                        <button className="p-2 rounded-full text-ink-subtle hover:text-ink hover:bg-surface-1 transition-colors">
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
                                    <div className="bg-canvas-soft/50 p-6 space-y-8 border-t border-hairline">
                                        {/* Truck Inventory Section */}
                                        <div>
                                            <h4 className="text-sm font-semibold text-ink mb-4 flex items-center gap-2">
                                                <Package className="w-4 h-4 text-ink-muted" />
                                                Inventario de ruta
                                                {routeItem.canLoad && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setLoadingRoute(routeItem)}
                                                        className="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-sm cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                    >
                                                        <PackagePlus className="w-3.5 h-3.5" />
                                                        {routeItem.loadHistory.length === 0 ? 'Registrar carga inicial' : 'Registrar recarga'}
                                                    </button>
                                                )}
                                            </h4>
                                            {routeItem.routeStock.length === 0 ? (
                                                <p className="text-sm text-ink-muted mb-4">
                                                    Todavía no se registró ninguna carga para esta ruta.
                                                </p>
                                            ) : (
                                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                                                    {routeItem.routeStock.map((stock) => (
                                                        <div
                                                            key={stock.productId}
                                                            className="bg-canvas border border-hairline/60 rounded-xl p-4 flex items-center justify-between shadow-sm glass"
                                                        >
                                                            <div>
                                                                <p className="text-sm font-medium text-ink">
                                                                    {stock.productName}
                                                                </p>
                                                                <p className="text-xs text-ink-muted mt-1">
                                                                    Cargado en el día: {stock.loaded} u.
                                                                </p>
                                                            </div>
                                                            <div className="text-right">
                                                                <span className="text-xs text-ink-muted">Remanente</span>
                                                                <p className="text-lg font-semibold text-ink">
                                                                    {stock.current} u.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            {routeItem.loadHistory.length > 0 && (
                                                <div className="bg-canvas border border-hairline/60 rounded-xl shadow-sm overflow-hidden glass">
                                                    <table className="w-full text-left text-sm">
                                                        <thead className="border-b border-hairline text-ink-muted">
                                                            <tr>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Hora</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Carga</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Producto</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium">Registró</th>
                                                                <th className="px-4 py-2.5 text-xs font-medium text-right">Unidades</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-hairline">
                                                            {routeItem.loadHistory.map((load) => (
                                                                <tr key={load.id}>
                                                                    <td className="px-4 py-2.5 text-ink-secondary whitespace-nowrap">
                                                                        {load.loadedAt ?? '—'}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-ink-secondary">
                                                                        {load.isInitial ? 'Inicial' : 'Recarga'}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 font-medium text-ink">
                                                                        {load.productName}
                                                                    </td>
                                                                    <td className="px-4 py-2.5 text-ink-secondary">{load.loadedBy ?? '—'}</td>
                                                                    <td className="px-4 py-2.5 text-right font-semibold text-ink">
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
                                            <h4 className="text-sm font-semibold text-ink mb-4 flex items-center gap-2">
                                                <MapPin className="w-4 h-4 text-ink-muted" />
                                                Hoja de Ruta y Paradas ({routeItem.stops.length})
                                            </h4>

                                            <div className="bg-canvas border border-hairline/60 rounded-xl overflow-hidden shadow-sm glass">
                                                <table className="w-full text-left text-sm">
                                                    <thead className="bg-canvas-soft/80 backdrop-blur-md text-ink-muted font-medium text-xs border-b border-hairline/60">
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
                                                                    className="px-6 py-8 text-center text-ink-muted"
                                                                >
                                                                    No hay paradas registradas en esta ruta.
                                                                </td>
                                                            </tr>
                                                        ) : (
                                                            routeItem.stops.map((stop, idx) => (
                                                                <tr
                                                                    key={stop.id}
                                                                    className="border-b border-hairline last:border-0 hover:bg-canvas-soft/50 transition-colors"
                                                                >
                                                                    <td className="px-6 py-4 text-center">
                                                                        <span className="inline-flex w-7 h-7 rounded-full bg-surface-1 text-ink-secondary items-center justify-center text-xs font-medium">
                                                                            {idx + 1}
                                                                        </span>
                                                                    </td>
                                                                    <td className="px-6 py-4">
                                                                        <span className="font-medium text-ink block">
                                                                            {stop.customer.name}
                                                                        </span>
                                                                        {stop.customer.phone && (
                                                                            <span className="text-xs text-ink-muted mt-0.5 block">
                                                                                {stop.customer.phone}
                                                                            </span>
                                                                        )}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-ink-muted">
                                                                        {stop.customer.address}
                                                                    </td>
                                                                    <td className="px-6 py-4">
                                                                        <StatusBadge status={stop.status} />
                                                                    </td>
                                                                    <td className="px-6 py-4 text-ink-muted">
                                                                        {stop.arrivedAt ? `${stop.arrivedAt} hs` : '-'}
                                                                    </td>
                                                                    <td className="px-6 py-4 text-ink-muted">
                                                                        {stop.outcome ? (
                                                                            <span className="font-medium text-ink text-sm">
                                                                                {stop.outcome}
                                                                            </span>
                                                                        ) : (
                                                                            <span className="text-ink-subtle italic">
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
