import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { StatusBadge } from '@/ui/Badge';
import { PageHeader } from '@/ui/PageHeader';
import {
    ArrowLeft,
    Truck,
    MapPin,
    Users,
    Package,
    Calendar,
} from 'lucide-react';
import { money } from '@/lib/format';
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
        email?: string;
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
                <div className="flex items-center gap-2 text-sm">
                    <Link
                        href={route('drivers.index')}
                        className="text-slate-500 hover:text-slate-900 font-medium transition-colors"
                    >
                        Choferes
                    </Link>
                    <span className="text-slate-400">/</span>
                    <span className="font-medium text-slate-900">{repartidor.name}</span>
                </div>
            }
        >
            <Head title={`${repartidor.name} - Detalle de Chofer`} />

            {/* Back link */}
            <div className="mb-4">
                <Link
                    href={route('drivers.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Volver al listado
                </Link>
            </div>

            {/* Driver Profile Header Card - Impeccable Cloud */}
            <div className="bg-white/80 backdrop-blur-md rounded-2xl p-6 mb-6 shadow-sm border border-gray-200/60">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-lg bg-slate-100 text-slate-700 shadow-sm flex items-center justify-center text-xl font-medium">
                            {repartidor.name.charAt(0)}
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">
                                    {repartidor.name}
                                </h1>
                                {deliveryRoute && <StatusBadge status={deliveryRoute.status} />}
                            </div>
                            <div className="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-500 mt-2 tracking-wide">
                                {repartidor.email && (
                                    <span>{repartidor.email}</span>
                                )}
                                <span className="text-gray-300">•</span>
                                <div className="flex items-center gap-1.5">
                                    <Users className="w-4 h-4 text-slate-400" />
                                    <span>{repartidor.totalAssignedCustomers} clientes fijos</span>
                                </div>
                                <span className="text-gray-300">•</span>
                                <div className="flex items-center gap-1.5">
                                    <Calendar className="w-4 h-4 text-slate-400" />
                                    <span>Fecha: {targetDate}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Summary Stats */}
                    {deliveryRoute && (
                        <div className="flex items-center gap-8 border-t md:border-t-0 md:border-l border-slate-700 pt-4 md:pt-0 md:pl-8">
                            <div>
                                <span className="text-xs font-medium text-slate-500 tracking-wide">
                                    Ventas Hoy
                                </span>
                                <p className="text-xl font-semibold text-slate-900 mt-0.5">
                                    {money(deliveryRoute.totalSalesAmount)}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium text-slate-500 tracking-wide">
                                    Cobranza en Mano
                                </span>
                                <p className="text-xl font-medium text-emerald-600 mt-0.5">
                                    {money(deliveryRoute.totalCollectedAmount)}
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* If no route today */}
            {!deliveryRoute ? (
                <div className="bg-white rounded-lg border border-gray-200 p-12 text-center shadow-sm">
                    <Truck className="w-12 h-12 mx-auto text-gray-300 mb-4" />
                    <h3 className="text-lg font-medium text-slate-900">Sin ruta asignada para hoy</h3>
                    <p className="text-sm text-slate-500 mt-2">
                        El chofer no tiene una hoja de ruta planificada para la fecha seleccionada ({targetDate}).
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Stops Timeline Table (2 Cols) */}
                    <div className="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                        <div className="p-5 bg-white/80 backdrop-blur-md border-b border-gray-100 text-slate-900 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900 tracking-wide">
                                    Hoja de Ruta & Paradas
                                </h2>
                                <p className="text-xs font-medium text-slate-500 mt-1 tracking-wide">
                                    {completedStops} de {totalStops} paradas ({progressPercent}%)
                                </p>
                            </div>
                            {/* Progress bar */}
                            <div className="w-48">
                                <div className="w-full h-2.5 bg-slate-100 rounded-lg overflow-hidden">
                                    <div
                                        className="h-full bg-slate-900 rounded-lg transition-all duration-300"
                                        style={{ width: `${progressPercent}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-gray-50/50 border-b border-gray-100 text-slate-500 font-medium tracking-wide">
                                    <tr>
                                        <th className="px-6 py-4"># Parada</th>
                                        <th className="px-6 py-4">Cliente y Dirección</th>
                                        <th className="px-6 py-4">Estado</th>
                                        <th className="px-6 py-4 text-right">Venta / Cobro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {deliveryRoute.stops.map((stop, idx) => (
                                        <tr key={stop.id} className="hover:bg-gray-50 transition-colors">
                                            {/* Stop number & Time */}
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <span className="w-8 h-8 rounded-lg bg-slate-100 text-gray-600 flex items-center justify-center text-sm font-medium">
                                                        {idx + 1}
                                                    </span>
                                                    {stop.arrivedAt && (
                                                        <span className="text-xs text-slate-500 font-medium">
                                                            {stop.arrivedAt} hs
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            {/* Customer */}
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={route('customers.show', stop.customer.id)}
                                                    className="font-semibold text-slate-900 hover:text-slate-900 transition-colors"
                                                >
                                                    {stop.customer.name}
                                                </Link>
                                                {stop.customer.address && (
                                                    <div className="flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                                                        <MapPin className="w-3.5 h-3.5 text-slate-400" />
                                                        <span>{stop.customer.address}</span>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Status */}
                                            <td className="px-6 py-4">
                                                <StatusBadge status={stop.status} />
                                            </td>

                                            {/* Sales / Collections */}
                                            <td className="px-6 py-4 text-right">
                                                {stop.salesAmount > 0 || stop.paymentsAmount > 0 ? (
                                                    <div>
                                                        <p className="font-semibold text-slate-900">
                                                            +{money(stop.salesAmount)}
                                                        </p>
                                                        {stop.paymentsAmount > 0 && (
                                                            <p className="text-xs font-medium text-emerald-600 mt-0.5">
                                                                Cobrado: {money(stop.paymentsAmount)}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-300 font-medium">-</span>
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
                        <div className="bg-white rounded-lg border border-gray-200 shadow-sm">
                            <div className="p-5 bg-white/80 backdrop-blur-md border-b border-gray-100 text-slate-900 flex items-center gap-2.5">
                                <Package className="w-5 h-5 text-slate-400" />
                                <h3 className="text-base font-semibold text-slate-900 tracking-wide">
                                    Carga y Stock
                                </h3>
                            </div>

                            <div className="p-5">
                                {deliveryRoute.inventory.length === 0 ? (
                                    <p className="text-sm text-slate-500 text-center py-4">
                                        No hay registros de carga.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {deliveryRoute.inventory.map((inv) => (
                                            <div
                                                key={inv.id}
                                                className="flex items-center justify-between pb-3  last:pb-0"
                                            >
                                                <span className="text-sm font-medium text-gray-700">
                                                    {inv.productName}
                                                </span>
                                                <span className="text-sm font-semibold text-slate-900">
                                                    {inv.currentQuantity} <span className="text-xs font-normal text-slate-500 ml-1">unid.</span>
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
