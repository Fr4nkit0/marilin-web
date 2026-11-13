import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useListFilters } from '@/lib/useListFilters';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { KpiCard } from '@/ui/KpiCard';
import { PageHeader } from '@/ui/PageHeader';
import RouteStockLoadModal, { LoadableProduct } from '@/modules/Street/RouteStock/components/RouteStockLoadModal';
import {
    CalendarCheck,
    Truck,
    ArrowUpRight,
    Clock,
    CheckCircle2,
    Package,
    Navigation,
    Calendar,
    ChevronDown,
    PackagePlus,
    Send,
    Split,
} from 'lucide-react';
import MoveStopsBar, { type DriverOption } from './components/MoveStopsBar';
import RouteStopsTable, { type RouteStop } from './components/RouteStopsTable';
import type { RouteStatus } from './types';

interface RouteStockItem {
    productId: number;
    productName: string;
    loaded: number;
    current: number;
}

interface LoadHistoryItem {
    id: number;
    isInitial: boolean;
    /** A load written down short, fixed from Revisión; it carries the admin's reason. */
    isCorrection: boolean;
    reason: string | null;
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
    /** The app gets the route only once the admin dispatches it, after the truck load. */
    dispatchedAt: string | null;
    dispatchedBy: string | null;
    canDispatch: boolean;
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
    /** "Cobertura": a planned route not dispatched yet can lend its stops to another driver. */
    canMoveStops: boolean;
    currentStock: StockItem[];
    stops: RouteStop[];
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

/** A correction alone is not a first load: the next load the admin registers is still the initial one. */
function hasLoads(route: RouteItem): boolean {
    return route.loadHistory.some((load) => !load.isCorrection);
}

/** A planned route the driver cannot see yet: it waits for the admin to dispatch it. */
function awaitsDispatch(route: RouteItem): boolean {
    return !route.dispatchedAt && route.status === 'PLANNED';
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
    const [dispatchingRouteId, setDispatchingRouteId] = useState<number | null>(null);
    const pageErrors = usePage().props.errors as Record<string, string>;

    const dispatchRoute = (routeItem: RouteItem) => {
        router.post(route('routes.dispatch', routeItem.id), {}, {
            preserveScroll: true,
            onStart: () => setDispatchingRouteId(routeItem.id),
            onFinish: () => setDispatchingRouteId(null),
        });
    };
    const filter = useListFilters(
        'routes.index',
        { date: selectedDate, status: filters.status || 'all', driver_id: filters.driver_id || '' },
        { date: '', status: 'all', driver_id: '' },
    );
    const [expandedRouteId, setExpandedRouteId] = useState<number | null>(
        routes.length > 0 ? routes[0].id : null
    );
    // The stops picked to move belong to the one expanded route.
    const [selectedStopIds, setSelectedStopIds] = useState<number[]>([]);

    const toggleRouteExpand = (id: number) => {
        setExpandedRouteId(expandedRouteId === id ? null : id);
        setSelectedStopIds([]);
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink-muted">
                        Despacho y rutas
                    </span>
                </div>
            }
        >
            <Head title="Despacho y rutas" />

            <PageHeader
                title="Despacho y rutas"
                description={`Supervisión de paradas, inventario de ruta y avance de recorridos para la fecha seleccionada.`}
            />

            {/* A route that could no longer be split when its page was opened. */}
            {pageErrors.split && (
                <p role="alert" className="mb-6 rounded-xl border border-rose-200/60 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-300">
                    {pageErrors.split}
                </p>
            )}

            {/* Quick Metrics */}
            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
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
            <div className="bg-canvas border border-hairline/60 rounded-2xl p-4 mb-6 shadow-sm ">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        {/* Date Picker */}
                        <div className="flex w-full sm:w-auto items-center gap-2 bg-canvas border border-hairline/60 rounded-lg px-3 py-2 text-sm text-ink focus-within:ring-2 focus-within:ring-sky-600 focus-within:border-transparent transition-all shadow-sm">
                            <Calendar className="w-4 h-4 text-ink-subtle" />
                            <input
                                type="date"
                                value={filter.values.date}
                                onChange={(e) => filter.set('date', e.target.value)}
                                aria-label="Fecha"
                                style={{ colorScheme: 'light' }}
                                className="flex-1 bg-transparent border-none p-0 text-base sm:text-sm font-medium focus:ring-0 focus:outline-none cursor-pointer text-ink"
                            />
                        </div>

                        {/* Status Filter */}
                        <div className="flex w-full sm:w-auto items-center gap-2">
                            <select
                                value={filter.values.status}
                                onChange={(e) => filter.set('status', e.target.value)}
                                aria-label="Estado"
                                className="w-full sm:w-auto text-sm font-medium border border-hairline/60 rounded-lg py-2 pl-3 pr-8 bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent transition-all shadow-sm"
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
                            aria-label="Repartidor"
                            className="w-full sm:w-auto text-sm font-medium border border-hairline/60 rounded-lg py-2 pl-3 pr-8 bg-canvas text-ink focus:outline-none focus:ring-2 focus:ring-sky-600 focus:border-transparent transition-all shadow-sm"
                        >
                            <option value="">Todos los repartidores</option>
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
                        className="min-h-11 sm:min-h-0 text-sm font-medium text-ink-muted hover:text-ink transition-colors"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            {/* Routes List */}
            {routes.length === 0 ? (
                <div className="bg-canvas border border-hairline/60 rounded-2xl p-12 text-center shadow-sm ">
                    <Truck className="w-12 h-12 text-ink-subtle mx-auto mb-4" />
                    <h3 className="text-lg font-semibold text-ink">No hay rutas asignadas para esta fecha</h3>
                    <p className="text-sm text-ink-muted mt-2">
                        Probá seleccionando otra fecha o cambiando los filtros de repartidor/estado.
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    {routes.map((routeItem) => {
                        const isExpanded = expandedRouteId === routeItem.id;

                        return (
                            <div
                                key={routeItem.id}
                                className={`bg-canvas border rounded-2xl shadow-sm overflow-hidden transition-colors duration-200 ${
                                    isExpanded ? 'border-hairline-strong' : 'border-hairline/60'
                                }`}
                            >
                                {/* Route summary: a fixed grid so the progress column lines up across routes. */}
                                <div
                                    onClick={() => toggleRouteExpand(routeItem.id)}
                                    className="px-5 py-4 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-6 gap-y-3 cursor-pointer hover:bg-canvas-soft/50 transition-colors lg:grid-cols-[minmax(0,1fr)_15rem_auto]"
                                >
                                    {/* Driver, state and times */}
                                    <div className="flex items-center gap-4 min-w-0">
                                        <div className="hidden sm:flex w-10 h-10 shrink-0 rounded-full bg-surface-1 text-ink-secondary items-center justify-center font-semibold">
                                            {routeItem.driver.name.charAt(0)}
                                        </div>
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <h3 className="text-base font-semibold text-ink truncate">
                                                    {routeItem.driver.name}
                                                </h3>
                                                {awaitsDispatch(routeItem) ? (
                                                    <StatusBadge status="pendiente" label="Sin despachar" />
                                                ) : (
                                                    <StatusBadge status={routeItem.status} />
                                                )}
                                            </div>
                                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-muted mt-1 tabular-nums">
                                                <span className="font-medium text-ink-secondary">Ruta #{routeItem.id}</span>
                                                {routeItem.dispatchedAt && (
                                                    <span className="inline-flex items-center gap-1.5" title="Hora de despacho">
                                                        <Send className="w-3.5 h-3.5 text-ink-subtle" />
                                                        Despachada {routeItem.dispatchedAt} hs
                                                    </span>
                                                )}
                                                {routeItem.startedAt ? (
                                                    <span className="inline-flex items-center gap-1.5" title="Primera y última parada">
                                                        <Clock className="w-3.5 h-3.5 text-ink-subtle" />
                                                        {routeItem.startedAt}
                                                        {routeItem.completedAt ? ` – ${routeItem.completedAt}` : ''} hs
                                                    </span>
                                                ) : (
                                                    <span className="text-ink-subtle">Sin iniciar</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Stops progress */}
                                    <div className="col-span-2 row-start-2 lg:col-span-1 lg:row-start-auto">
                                        <div className="flex items-baseline justify-between text-xs text-ink-muted mb-1.5">
                                            <span>Paradas</span>
                                            <span className="tabular-nums">
                                                <span className="text-sm font-semibold text-ink">
                                                    {routeItem.stats.completedStops}/{routeItem.stats.totalStops}
                                                </span>{' '}
                                                · {routeItem.stats.progressPercent}%
                                            </span>
                                        </div>
                                        <div
                                            role="progressbar"
                                            aria-label={`Paradas completadas de ${routeItem.driver.name}`}
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                            aria-valuenow={routeItem.stats.progressPercent}
                                            className="w-full h-1.5 bg-surface-1 rounded-full overflow-hidden"
                                        >
                                            <div
                                                className={`h-full rounded-full transition-[width] duration-300 ${
                                                    routeItem.stats.progressPercent === 100
                                                        ? 'bg-emerald-500 dark:bg-emerald-400'
                                                        : 'bg-sky-600 dark:bg-sky-400'
                                                }`}
                                                style={{ width: `${routeItem.stats.progressPercent}%` }}
                                            />
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex items-center gap-1 justify-self-end">
                                        {routeItem.canMoveStops && (
                                            <Link
                                                href={route('routes.split', routeItem.id)}
                                                onClick={(e) => e.stopPropagation()}
                                                aria-label={`Repartir ruta de ${routeItem.driver.name}`}
                                                className="inline-flex min-h-11 min-w-11 sm:min-h-0 sm:min-w-0 items-center justify-center gap-1 p-2 sm:px-3 rounded-lg text-sm font-medium text-sky-700 hover:text-sky-800 hover:bg-sky-50 dark:text-sky-300 dark:hover:text-sky-200 dark:hover:bg-sky-500/15 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
                                            >
                                                <Split className="w-4 h-4" />
                                                <span className="hidden sm:inline">Repartir ruta</span>
                                            </Link>
                                        )}
                                        {routeItem.driver.id && (
                                            <Link
                                                href={route('drivers.show', routeItem.driver.id)}
                                                onClick={(e) => e.stopPropagation()}
                                                aria-label={`Ver ficha de ${routeItem.driver.name}`}
                                                className="inline-flex min-h-11 min-w-11 sm:min-h-0 sm:min-w-0 items-center justify-center gap-1 p-2 sm:px-3 rounded-lg text-sm font-medium text-ink-secondary hover:text-ink hover:bg-surface-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
                                            >
                                                <span className="hidden sm:inline">Ver ficha</span>
                                                <ArrowUpRight className="w-4 h-4" />
                                            </Link>
                                        )}
                                        <button
                                            type="button"
                                            aria-label={`${isExpanded ? 'Ocultar' : 'Ver'} detalle de la ruta de ${routeItem.driver.name}`}
                                            aria-expanded={isExpanded}
                                            className="inline-flex min-h-11 min-w-11 sm:min-h-0 sm:min-w-0 items-center justify-center p-2 rounded-lg text-ink-subtle hover:text-ink hover:bg-surface-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-600"
                                        >
                                            <ChevronDown
                                                className={`w-5 h-5 transition-transform duration-200 ${isExpanded ? 'rotate-180' : ''}`}
                                            />
                                        </button>
                                    </div>
                                </div>

                                {/* Expanded Details: Truck Inventory & Stops Timeline */}
                                {isExpanded && (
                                    <div className="bg-canvas-soft/50 p-4 sm:p-6 space-y-8 border-t border-hairline">
                                        {/* Truck Inventory Section */}
                                        <div>
                                            <h4 className="text-sm font-semibold text-ink mb-4 flex flex-wrap items-center gap-2">
                                                <Package className="w-4 h-4 text-ink-muted" />
                                                Inventario de ruta
                                                {routeItem.canLoad && (
                                                    <button
                                                        type="button"
                                                        onClick={() => setLoadingRoute(routeItem)}
                                                        className="ml-auto inline-flex min-h-11 sm:min-h-0 items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-medium shadow-sm cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                    >
                                                        <PackagePlus className="w-3.5 h-3.5" />
                                                        {hasLoads(routeItem) ? 'Registrar recarga' : 'Registrar carga inicial'}
                                                    </button>
                                                )}
                                                {routeItem.canDispatch && (
                                                    <button
                                                        type="button"
                                                        onClick={() => dispatchRoute(routeItem)}
                                                        disabled={dispatchingRouteId === routeItem.id}
                                                        className={`${routeItem.canLoad ? '' : 'ml-auto '}inline-flex min-h-11 sm:min-h-0 items-center gap-1.5 px-3 py-1.5 rounded-lg bg-canvas border border-sky-600 text-sky-700 hover:bg-sky-50 dark:border-sky-500/50 dark:text-sky-300 dark:hover:bg-sky-500/15 text-xs font-medium shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-wait`}
                                                    >
                                                        <Send className="w-3.5 h-3.5" />
                                                        Despachar ruta
                                                    </button>
                                                )}
                                            </h4>
                                            {awaitsDispatch(routeItem) && (
                                                <p className="text-sm text-ink-muted mb-4">
                                                    El repartidor recibe la ruta en la app cuando la despachás, después de registrar la carga inicial.
                                                </p>
                                            )}
                                            {pageErrors.route && dispatchingRouteId === null && expandedRouteId === routeItem.id && (
                                                <p role="alert" className="text-sm text-rose-600 dark:text-rose-400 mb-4">
                                                    {pageErrors.route}
                                                </p>
                                            )}
                                            {routeItem.routeStock.length === 0 ? (
                                                <p className="text-sm text-ink-muted mb-4">
                                                    Todavía no se registró ninguna carga para esta ruta.
                                                </p>
                                            ) : (
                                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                                                    {routeItem.routeStock.map((stock) => (
                                                        <div
                                                            key={stock.productId}
                                                            className="bg-canvas border border-hairline/60 rounded-xl p-4 flex items-center justify-between shadow-sm "
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
                                                                <p className="text-lg font-semibold text-ink tabular-nums">
                                                                    {stock.current} u.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}

                                            {routeItem.loadHistory.length > 0 && (
                                                <div className="bg-canvas border border-hairline/60 rounded-xl shadow-sm table-scroll">
                                                    <table className="table-cards w-full text-left text-sm">
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
                                                                    <td data-label="Hora" className="px-4 py-2.5 text-ink-secondary whitespace-nowrap">
                                                                        {load.loadedAt ?? '—'}
                                                                    </td>
                                                                    <td data-label="Carga" className="px-4 py-2.5 text-ink-secondary">
                                                                        {load.isCorrection ? (
                                                                            <>
                                                                                Corrección
                                                                                {load.reason && (
                                                                                    <span className="block text-xs text-ink-muted max-sm:text-right">{load.reason}</span>
                                                                                )}
                                                                            </>
                                                                        ) : load.isInitial ? (
                                                                            'Inicial'
                                                                        ) : (
                                                                            'Recarga'
                                                                        )}
                                                                    </td>
                                                                    <td data-primary className="px-4 py-2.5 font-medium text-ink max-sm:order-first">
                                                                        {load.productName}
                                                                    </td>
                                                                    <td data-label="Registró" className="px-4 py-2.5 text-ink-secondary">{load.loadedBy ?? '—'}</td>
                                                                    <td data-label="Unidades" className="px-4 py-2.5 text-right font-semibold text-ink tabular-nums">
                                                                        +{load.quantity}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}
                                        </div>

                                        {routeItem.canMoveStops && routeItem.stops.length > 0 && (
                                            <MoveStopsBar
                                                routeId={routeItem.id}
                                                drivers={drivers.filter((driver) => driver.id !== routeItem.driver.id)}
                                                selected={selectedStopIds}
                                                onMoved={() => setSelectedStopIds([])}
                                            />
                                        )}
                                        <RouteStopsTable
                                            stops={routeItem.stops}
                                            selected={selectedStopIds}
                                            onSelect={routeItem.canMoveStops ? setSelectedStopIds : undefined}
                                        />
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
                isFirstLoad={loadingRoute === null || !hasLoads(loadingRoute)}
                products={products}
                onClose={() => setLoadingRoute(null)}
            />
        </AdminLayout>
    );
}
