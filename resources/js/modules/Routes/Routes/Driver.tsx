import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { PageHeader } from '@/ui/PageHeader';
import { buttonClass } from '@/ui/Button';
import {
    ArrowLeft,
    ArrowRightLeft,
    Truck,
    MapPin,
    Users,
    Package,
} from 'lucide-react';
import { date, money, stopStatusLabel } from '@/lib/format';
import RequestedVisitMark from './components/RequestedVisitMark';
import DayPicker from './components/DayPicker';
import type { RouteStatus, RouteStopRow } from './types';

interface StopItem extends RouteStopRow {
    salesAmount: number;
    paymentsAmount: number;
}

interface InventoryItem {
    id: number;
    productName: string;
    currentQuantity: number;
}

interface Props {
    repartidor: {
        id: number;
        name: string;
        username: string | null;
        totalAssignedCustomers: number;
    };
    route?: {
        id: number;
        date: string;
        status: RouteStatus;
        startedAt?: string;
        completedAt?: string;
        totalSalesAmount: number;
        totalCollectedAmount: number;
        totalStops: number;
        completedStops: number;
        progressPercent: number;
        stops: StopItem[];
        inventory: InventoryItem[];
    } | null;
    targetDate: string;
}

export default function RepartidorShow({ repartidor, route: deliveryRoute, targetDate }: Props) {
    const totalStops = deliveryRoute?.totalStops ?? 0;
    const completedStops = deliveryRoute?.completedStops ?? 0;
    const progressPercent = deliveryRoute?.progressPercent ?? 0;

    return (
        <AdminLayout
            header={
                <div className="flex min-w-0 items-center gap-2 text-sm">
                    <Link
                        href={route('drivers.index')}
                        className="shrink-0 text-ink-muted hover:text-ink font-medium transition-colors"
                    >
                        Repartidores
                    </Link>
                    <span className="shrink-0 text-ink-subtle">/</span>
                    <span className="min-w-0 truncate font-medium text-ink">{repartidor.name}</span>
                </div>
            }
        >
            <Head title={repartidor.name} />

            {/* Back link */}
            <div className="mb-4">
                <Link
                    href={route('drivers.index')}
                    className="inline-flex min-h-11 sm:min-h-0 items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Volver al listado
                </Link>
            </div>

            {/* Driver Profile Header Card - Impeccable Cloud */}
            <div className="bg-canvas/80 backdrop-blur-md rounded-2xl p-6 mb-6 shadow-sm border border-hairline/60 ">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-lg bg-surface-1 text-ink-secondary shadow-sm flex items-center justify-center text-xl font-medium">
                            {repartidor.name.charAt(0)}
                        </div>
                        <div>
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                <h1 className="text-2xl font-semibold text-ink tracking-tight">
                                    {repartidor.name}
                                </h1>
                                {deliveryRoute && <StatusBadge status={deliveryRoute.status} />}
                            </div>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-medium text-ink-muted mt-2 tracking-wide">
                                {repartidor.username && (
                                    <span>{repartidor.username}</span>
                                )}
                                <span className="hidden sm:inline text-ink-subtle">•</span>
                                <div className="flex items-center gap-1.5">
                                    <Users className="w-4 h-4 text-ink-subtle" />
                                    <span>{repartidor.totalAssignedCustomers} clientes fijos</span>
                                </div>
                                {repartidor.totalAssignedCustomers > 0 && (
                                    <Link href={route('customers.transfer', repartidor.id)} className={`${buttonClass('secondary', 'sm')} min-h-11 sm:min-h-0`}>
                                        <ArrowRightLeft className="w-3.5 h-3.5" />
                                        Transferir cartera
                                    </Link>
                                )}
                                <DayPicker
                                    value={targetDate}
                                    onChange={(day) =>
                                        router.get(route('drivers.show', repartidor.id), { date: day }, { preserveScroll: true, replace: true })
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    {/* Summary Stats */}
                    {deliveryRoute && (
                        <div className="flex items-center gap-8 border-t md:border-t-0 md:border-l border-hairline pt-4 md:pt-0 md:pl-8">
                            <div>
                                <span className="text-xs font-medium text-ink-muted tracking-wide">
                                    Ventas del día
                                </span>
                                <p className="text-xl font-semibold text-ink mt-0.5">
                                    {money(deliveryRoute.totalSalesAmount)}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium text-ink-muted tracking-wide">
                                    Cobrado
                                </span>
                                <p className="text-xl font-medium text-emerald-600 dark:text-emerald-300 mt-0.5">
                                    {money(deliveryRoute.totalCollectedAmount)}
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* If no route today */}
            {!deliveryRoute ? (
                <div className="bg-canvas rounded-lg border border-hairline p-12 text-center shadow-sm">
                    <Truck className="w-12 h-12 mx-auto text-ink-subtle mb-4" />
                    <h3 className="text-lg font-medium text-ink">{targetDate === new Date().toLocaleDateString('en-CA') ? 'Sin ruta asignada para hoy' : 'Sin ruta asignada para esta fecha'}</h3>
                    <p className="text-sm text-ink-muted mt-2">
                        El repartidor no tiene una hoja de ruta planificada para la fecha seleccionada ({date(targetDate)}).
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Stops Timeline Table (2 Cols) */}
                    <div className="lg:col-span-2 bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                        <div className="p-5 bg-canvas/80 backdrop-blur-md border-b border-hairline text-ink flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-ink tracking-wide">
                                    Hoja de Ruta & Paradas
                                </h2>
                                <p className="text-xs font-medium text-ink-muted mt-1 tracking-wide">
                                    {completedStops} de {totalStops} paradas ({progressPercent}%)
                                </p>
                            </div>
                            {/* Progress bar */}
                            <div className="w-24 sm:w-48 shrink-0">
                                <div className="w-full h-2.5 bg-surface-1 rounded-lg overflow-hidden">
                                    <div
                                        className="h-full bg-sky-600 rounded-lg transition-all duration-300 dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                        style={{ width: `${progressPercent}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="table-scroll">
                            <table className="table-cards w-full text-left text-sm">
                                <thead className="bg-canvas-soft/50 border-b border-hairline text-ink-muted font-medium tracking-wide">
                                    <tr>
                                        <th className="px-6 py-4"># Parada</th>
                                        <th className="px-6 py-4">Cliente y Dirección</th>
                                        <th className="px-6 py-4">Estado</th>
                                        <th className="px-6 py-4 text-right">Venta / Cobro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {deliveryRoute.stops.map((stop, idx) => (
                                        <tr
                                            key={stop.id}
                                            className="hover:bg-canvas-soft transition-colors max-sm:border-b max-sm:border-hairline max-sm:last:border-0"
                                        >
                                            {/* Stop number & Time */}
                                            <td data-label="# Parada" className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <span className="w-8 h-8 rounded-lg bg-surface-1 text-ink-secondary flex items-center justify-center text-sm font-medium">
                                                        {idx + 1}
                                                    </span>
                                                    {stop.arrivedAt && (
                                                        <span className="text-xs text-ink-muted font-medium">
                                                            {stop.arrivedAt} hs
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            {/* Customer: on a phone card it is the title, above the stop number. */}
                                            <td data-primary className="px-6 py-4 max-sm:order-first">
                                                <Link
                                                    href={route('customers.show', stop.customer.id)}
                                                    className="font-semibold text-ink hover:text-ink transition-colors max-sm:inline-flex max-sm:min-h-11 max-sm:items-center"
                                                >
                                                    {stop.customer.name}
                                                </Link>
                                                {stop.customer.address && (
                                                    <div className="flex items-center gap-1.5 text-xs font-normal text-ink-muted mt-1">
                                                        <MapPin className="w-3.5 h-3.5 text-ink-subtle" />
                                                        <span>{stop.customer.address}</span>
                                                    </div>
                                                )}
                                                {stop.is_requested && <RequestedVisitMark note={stop.note} />}
                                            </td>

                                            {/* Status */}
                                            <td data-label="Estado" className="px-6 py-4">
                                                <StatusBadge status={stop.status} label={stopStatusLabel(stop.status)} />
                                            </td>

                                            {/* Sales / Collections */}
                                            <td data-label="Venta / Cobro" className="px-6 py-4 text-right">
                                                {stop.salesAmount > 0 || stop.paymentsAmount > 0 ? (
                                                    <div>
                                                        <p className="font-semibold text-ink">
                                                            +{money(stop.salesAmount)}
                                                        </p>
                                                        {stop.paymentsAmount > 0 && (
                                                            <p className="text-xs font-medium text-emerald-600 dark:text-emerald-300 mt-0.5">
                                                                Cobrado: {money(stop.paymentsAmount)}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-ink-subtle font-medium">-</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Right: Truck Inventory Card (1 Col) */}
                    <div className="space-y-6">
                        <div className="bg-canvas rounded-lg border border-hairline shadow-sm">
                            <div className="p-5 bg-canvas/80 backdrop-blur-md border-b border-hairline text-ink flex items-center gap-2.5">
                                <Package className="w-5 h-5 text-ink-subtle" />
                                <h3 className="text-base font-semibold text-ink tracking-wide">
                                    Carga y Stock
                                </h3>
                            </div>

                            <div className="p-5">
                                {deliveryRoute.inventory.length === 0 ? (
                                    <p className="text-sm text-ink-muted text-center py-4">
                                        No hay registros de carga.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {deliveryRoute.inventory.map((inv) => (
                                            <div
                                                key={inv.id}
                                                className="flex items-center justify-between pb-3  last:pb-0"
                                            >
                                                <span className="text-sm font-medium text-ink-secondary">
                                                    {inv.productName}
                                                </span>
                                                <span className="text-sm font-semibold text-ink">
                                                    {inv.currentQuantity} <span className="text-xs font-normal text-ink-muted ml-1">unid.</span>
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
