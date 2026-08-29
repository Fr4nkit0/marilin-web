import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, StatusBadge } from '@/Components/StitchUi';
import {
    ArrowLeft,
    Truck,
    MapPin,
    Users,
    Package,
    DollarSign,
    CheckCircle2,
    Clock,
    CircleDollarSign,
    Phone,
    Calendar,
} from 'lucide-react';

interface StopItem {
    id: number;
    customer: {
        id: number;
        name: string;
        address?: string;
        phone?: string;
    };
    status: string; // 'PENDING', 'IN_PROGRESS', 'FINISHED', 'DELIVERED'
    arrivedAt?: string;
    salesAmount: number;
    paymentsAmount: number;
    notes?: string;
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
        status: string;
        startedAt?: string;
        completedAt?: string;
        totalSalesAmount: number;
        totalCollectedAmount: number;
        stops: StopItem[];
        inventory: InventoryItem[];
    } | null;
    targetDate: string;
}

export default function RepartidorShow({ repartidor, route: deliveryRoute, targetDate }: Props) {
    const totalStops = deliveryRoute?.stops.length || 0;
    const completedStops = deliveryRoute?.stops.filter(
        (s) => s.status === 'FINISHED' || s.status === 'DELIVERED' || s.status === 'COMPLETED'
    ).length || 0;

    const progressPercent = totalStops > 0 ? roundPercent((completedStops / totalStops) * 100) : 0;

    function roundPercent(val: number) {
        return Math.round(val);
    }

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2 text-xs">
                    <Link
                        href={route('repartidores.index')}
                        className="text-slate-500 hover:text-blue-600 font-medium"
                    >
                        Repartidores
                    </Link>
                    <span className="text-slate-400">/</span>
                    <span className="font-semibold text-slate-800">{repartidor.name}</span>
                </div>
            }
        >
            <Head title={`${repartidor.name} - Detalle de Repartidor`} />

            {/* Back link */}
            <div className="mb-4">
                <Link
                    href={route('repartidores.index')}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800 transition"
                >
                    <ArrowLeft className="w-3.5 h-3.5" />
                    Volver al listado de repartidores
                </Link>
            </div>

            {/* Driver Profile Header Card */}
            <div className="bg-white rounded-lg border border-slate-200 p-6 mb-6 shadow-2xs">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-full bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center text-xl font-bold font-display">
                            {repartidor.name.charAt(0)}
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold font-display text-slate-900">
                                    {repartidor.name}
                                </h1>
                                {deliveryRoute && <StatusBadge status={deliveryRoute.status} />}
                            </div>
                            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500 mt-2">
                                {repartidor.email && (
                                    <span>{repartidor.email}</span>
                                )}
                                <span>•</span>
                                <div className="flex items-center gap-1">
                                    <Users className="w-3.5 h-3.5 text-slate-400" />
                                    <span>{repartidor.totalAssignedCustomers} clientes fijos en cartera</span>
                                </div>
                                <span>•</span>
                                <div className="flex items-center gap-1">
                                    <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                    <span>Fecha: {targetDate}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Summary Stats */}
                    {deliveryRoute && (
                        <div className="flex items-center gap-6 border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-6">
                            <div>
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                    Ventas Hoy
                                </span>
                                <p className="text-xl font-bold font-display text-slate-900">
                                    ${deliveryRoute.totalSalesAmount.toLocaleString('es-AR')}
                                </p>
                            </div>
                            <div>
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                    Cobranza en Mano
                                </span>
                                <p className="text-xl font-bold font-display text-emerald-600">
                                    ${deliveryRoute.totalCollectedAmount.toLocaleString('es-AR')}
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* If no route today */}
            {!deliveryRoute ? (
                <div className="bg-white rounded-lg border border-slate-200 p-12 text-center text-slate-400">
                    <Truck className="w-12 h-12 mx-auto text-slate-300 mb-3" />
                    <h3 className="text-base font-bold text-slate-700">Sin ruta asignada para hoy</h3>
                    <p className="text-xs text-slate-500 mt-1">
                        El chofer no tiene una hoja de ruta planificada para la fecha seleccionada ({targetDate}).
                    </p>
                </div>
            ) : (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Stops Timeline Table (2 Cols) */}
                    <div className="lg:col-span-2 bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                        <div className="p-4 sm:px-6 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-bold font-display text-slate-900">
                                    Hoja de Ruta & Paradas del Día
                                </h2>
                                <p className="text-xs text-slate-500">
                                    {completedStops} de {totalStops} paradas visitadas ({progressPercent}%)
                                </p>
                            </div>
                            {/* Progress bar */}
                            <div className="w-32">
                                <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-blue-600 rounded-full transition-all duration-300"
                                        style={{ width: `${progressPercent}%` }}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                    <tr>
                                        <th className="px-6 py-3.5"># Parada</th>
                                        <th className="px-6 py-3.5">Cliente y Dirección</th>
                                        <th className="px-6 py-3.5">Estado</th>
                                        <th className="px-6 py-3.5 text-right">Venta / Cobro</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {deliveryRoute.stops.map((stop, idx) => (
                                        <tr key={stop.id} className="hover:bg-slate-50/80 transition">
                                            {/* Stop number & Time */}
                                            <td className="px-6 py-4 font-bold text-slate-700">
                                                <div className="flex items-center gap-2">
                                                    <span className="w-6 h-6 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center text-[11px]">
                                                        {idx + 1}
                                                    </span>
                                                    {stop.arrivedAt && (
                                                        <span className="text-[11px] text-slate-400 font-normal">
                                                            {stop.arrivedAt} hs
                                                        </span>
                                                    )}
                                                </div>
                                            </td>

                                            {/* Customer */}
                                            <td className="px-6 py-4">
                                                <Link
                                                    href={route('customers.show', stop.customer.id)}
                                                    className="font-semibold text-slate-900 hover:text-blue-600 transition"
                                                >
                                                    {stop.customer.name}
                                                </Link>
                                                {stop.customer.address && (
                                                    <div className="flex items-center gap-1 text-[11px] text-slate-400 mt-0.5">
                                                        <MapPin className="w-3 h-3" />
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
                                                            +${stop.salesAmount.toLocaleString('es-AR')}
                                                        </p>
                                                        {stop.paymentsAmount > 0 && (
                                                            <p className="text-[11px] font-semibold text-emerald-600">
                                                                Cobrado: ${stop.paymentsAmount.toLocaleString('es-AR')}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400">-</span>
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
                        <div className="bg-white rounded-lg border border-slate-200 p-5 shadow-2xs">
                            <div className="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                                <Package className="w-5 h-5 text-blue-600" />
                                <h3 className="text-sm font-bold font-display text-slate-900">
                                    Carga y Stock en Camión
                                </h3>
                            </div>

                            {deliveryRoute.inventory.length === 0 ? (
                                <p className="text-xs text-slate-400 text-center py-4">
                                    No hay registros de carga en camión para esta ruta.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {deliveryRoute.inventory.map((inv) => (
                                        <div
                                            key={inv.id}
                                            className="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-100"
                                        >
                                            <span className="text-xs font-semibold text-slate-800">
                                                {inv.productName}
                                            </span>
                                            <span className="text-sm font-bold font-display text-blue-600">
                                                {inv.currentQuantity} <span className="text-[11px] font-normal text-slate-500">unid.</span>
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
