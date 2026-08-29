import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, DebtBadge, PaymentMethodBadge } from '@/Components/StitchUi';
import {
    ArrowLeft,
    Phone,
    MapPin,
    User,
    Package,
    Receipt,
    CircleDollarSign,
    Calendar,
    Tag,
    Clock,
    Plus,
    CreditCard,
    Truck,
} from 'lucide-react';

interface Props {
    customer: {
        id: number;
        name: string;
        phone_number?: string;
        primary_address?: {
            street: string;
            apartment?: string;
            floor?: number;
            visual_reference?: string;
            latitude?: number;
            longitude?: number;
        };
        driver?: {
            id: number;
            user?: {
                name: string;
            };
        };
        repartidor?: {
            id: number;
            user?: {
                name: string;
            };
        };
        balance?: {
            current_balance: string | number;
        };
        container_balances?: {
            id: number;
            balance: number;
            container_type?: {
                name: string;
                capacity_liters?: number;
            };
        }[];
        agreements?: {
            id: number;
            special_price: string | number;
            product?: {
                name: string;
            };
        }[];
    };
    accountEntries: {
        id: number;
        entryType: string; // 'SALE', 'PAYMENT', 'ADJUSTMENT'
        direction: number; // 1 debit (owes more), -1 credit (pays)
        amount: number;
        description?: string;
        createdAt: string;
        paymentMethod?: string;
    }[];
    containerMovements: {
        id: number;
        type: string; // 'DELIVERED', 'RETURNED'
        quantity: number;
        container_type?: {
            name: string;
        };
        created_at: string;
    }[];
}

export default function CustomerShow({ customer, accountEntries, containerMovements }: Props) {
    const [activeTab, setActiveTab] = useState<'account' | 'containers' | 'agreements'>('account');

    const balanceNum = Number(customer.balance?.current_balance || 0);
    const hasDebt = balanceNum > 0;

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2 text-xs">
                    <Link
                        href={route('customers.index')}
                        className="text-slate-500 hover:text-blue-600 font-medium"
                    >
                        Clientes
                    </Link>
                    <span className="text-slate-400">/</span>
                    <span className="font-semibold text-slate-800">{customer.name}</span>
                </div>
            }
        >
            <Head title={`${customer.name} - Detalle de Cliente`} />

            {/* Back button & Page Header */}
            <div className="mb-4">
                <Link
                    href={route('customers.index')}
                    className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800 transition"
                >
                    <ArrowLeft className="w-3.5 h-3.5" />
                    Volver al listado de clientes
                </Link>
            </div>

            {/* Top Customer 360 Header Card */}
            <div className="bg-white rounded-lg border border-slate-200 p-6 mb-6 shadow-2xs">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-full bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center text-xl font-bold font-display">
                            {customer.name.charAt(0)}
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold font-display text-slate-900">
                                    {customer.name}
                                </h1>
                                <DebtBadge hasDebt={hasDebt} balance={balanceNum} />
                            </div>
                            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500 mt-2">
                                {customer.phone_number && (
                                    <div className="flex items-center gap-1">
                                        <Phone className="w-3.5 h-3.5 text-slate-400" />
                                        <span>{customer.phone_number}</span>
                                    </div>
                                )}
                                {customer.primary_address && (
                                    <div className="flex items-center gap-1">
                                        <MapPin className="w-3.5 h-3.5 text-slate-400" />
                                        <span>
                                            {customer.primary_address.street}
                                            {customer.primary_address.apartment && ` (Depto ${customer.primary_address.apartment})`}
                                            {customer.primary_address.visual_reference && ` - ${customer.primary_address.visual_reference}`}
                                        </span>
                                    </div>
                                )}
                                {(customer.driver?.user || customer.repartidor?.user) && (
                                    <div className="flex items-center gap-1">
                                        <Truck className="w-3.5 h-3.5 text-slate-400" />
                                        <span>
                                            Repartidor: <strong className="text-slate-700">{customer.driver?.user?.name || customer.repartidor?.user?.name}</strong>
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Quick Stats on Right */}
                    <div className="flex items-center gap-4 border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-6">
                        <div className="text-right">
                            <span className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                Saldo Actual
                            </span>
                            <p className={`text-2xl font-bold font-display ${hasDebt ? 'text-rose-600' : 'text-emerald-600'}`}>
                                ${Math.abs(balanceNum).toLocaleString('es-AR')}
                            </p>
                            <span className="text-[11px] text-slate-400">
                                {hasDebt ? 'Deuda a favor de la empresa' : 'Al día / Sin saldo deudor'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Content Tabs Navigation */}
            <div className="border-b border-slate-200 mb-6 flex items-center gap-6 text-sm font-semibold">
                <button
                    onClick={() => setActiveTab('account')}
                    className={`pb-3 relative transition ${
                        activeTab === 'account'
                            ? 'text-blue-600 border-b-2 border-blue-600 font-bold'
                            : 'text-slate-500 hover:text-slate-800'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <Receipt className="w-4 h-4" />
                        Cuenta Corriente & Movimientos
                    </span>
                </button>
                <button
                    onClick={() => setActiveTab('containers')}
                    className={`pb-3 relative transition ${
                        activeTab === 'containers'
                            ? 'text-blue-600 border-b-2 border-blue-600 font-bold'
                            : 'text-slate-500 hover:text-slate-800'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <Package className="w-4 h-4" />
                        Envases Retornables
                    </span>
                </button>
                <button
                    onClick={() => setActiveTab('agreements')}
                    className={`pb-3 relative transition ${
                        activeTab === 'agreements'
                            ? 'text-blue-600 border-b-2 border-blue-600 font-bold'
                            : 'text-slate-500 hover:text-slate-800'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <Tag className="w-4 h-4" />
                        Acuerdos de Precios
                    </span>
                </button>
            </div>

            {/* TAB 1: Account Entries */}
            {activeTab === 'account' && (
                <div className="bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                    <div className="p-4 sm:px-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-bold font-display text-slate-900">
                                Libro de Movimientos Contables
                            </h3>
                            <p className="text-xs text-slate-500">
                                Historial cronológico de débitos por ventas y créditos por pagos o cobranzas.
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                                <tr>
                                    <th className="px-6 py-3.5">Fecha y Hora</th>
                                    <th className="px-6 py-3.5">Concepto</th>
                                    <th className="px-6 py-3.5">Tipo</th>
                                    <th className="px-6 py-3.5 text-center">Método</th>
                                    <th className="px-6 py-3.5 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {accountEntries.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-8 text-center text-slate-400">
                                            No hay movimientos registrados en la cuenta corriente.
                                        </td>
                                    </tr>
                                ) : (
                                    accountEntries.map((entry) => {
                                        const isDebit = entry.direction === 1; // Sale / increase debt
                                        return (
                                            <tr key={entry.id} className="hover:bg-slate-50/80 transition">
                                                <td className="px-6 py-3.5 text-slate-500 font-medium">
                                                    {entry.createdAt}
                                                </td>
                                                <td className="px-6 py-3.5 font-semibold text-slate-800">
                                                    {entry.description || (isDebit ? 'Venta de productos' : 'Cobranza / Pago recibido')}
                                                </td>
                                                <td className="px-6 py-3.5">
                                                    <span
                                                        className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${
                                                            isDebit
                                                                ? 'bg-rose-50 text-rose-700 border border-rose-100'
                                                                : 'bg-emerald-50 text-emerald-700 border border-emerald-100'
                                                        }`}
                                                    >
                                                        {entry.entryType}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-3.5 text-center">
                                                    {entry.paymentMethod ? (
                                                        <PaymentMethodBadge method={entry.paymentMethod} />
                                                    ) : (
                                                        <span className="text-slate-400">-</span>
                                                    )}
                                                </td>
                                                <td
                                                    className={`px-6 py-3.5 text-right font-bold text-sm ${
                                                        isDebit ? 'text-rose-600' : 'text-emerald-600'
                                                    }`}
                                                >
                                                    {isDebit ? '+' : '-'}${entry.amount.toLocaleString('es-AR')}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* TAB 2: Container Balances */}
            {activeTab === 'containers' && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Container Cards */}
                    <div className="md:col-span-1 space-y-4">
                        <h3 className="text-sm font-bold font-display text-slate-900">
                            Envases en Posesión del Cliente
                        </h3>
                        {customer.container_balances?.map((cb) => (
                            <div
                                key={cb.id}
                                className="bg-white rounded-lg border border-slate-200 p-4 shadow-2xs flex items-center justify-between"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="p-2.5 rounded-lg bg-blue-50 text-blue-600 border border-blue-100">
                                        <Package className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-bold text-slate-900">
                                            {cb.container_type?.name || 'Envase'}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            Capacidad: {cb.container_type?.capacity_liters || 20}L
                                        </p>
                                    </div>
                                </div>
                                <span className="text-xl font-bold font-display text-slate-900">
                                    {cb.balance} <span className="text-xs text-slate-400 font-normal">unid.</span>
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Movement History */}
                    <div className="md:col-span-2 bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                        <div className="p-4 sm:px-6 border-b border-slate-100">
                            <h3 className="text-sm font-bold font-display text-slate-900">
                                Últimos Movimientos de Envases
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase">
                                    <tr>
                                        <th className="px-6 py-3">Fecha</th>
                                        <th className="px-6 py-3">Envase</th>
                                        <th className="px-6 py-3">Tipo de Operación</th>
                                        <th className="px-6 py-3 text-right">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {containerMovements.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-6 py-6 text-center text-slate-400">
                                                Sin movimientos recientes.
                                            </td>
                                        </tr>
                                    ) : (
                                        containerMovements.map((cm) => (
                                            <tr key={cm.id} className="hover:bg-slate-50">
                                                <td className="px-6 py-3 text-slate-500">
                                                    {cm.created_at}
                                                </td>
                                                <td className="px-6 py-3 font-medium text-slate-800">
                                                    {cm.container_type?.name}
                                                </td>
                                                <td className="px-6 py-3">
                                                    <span
                                                        className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                                            cm.type === 'DELIVERED'
                                                                ? 'bg-blue-50 text-blue-700'
                                                                : 'bg-emerald-50 text-emerald-700'
                                                        }`}
                                                    >
                                                        {cm.type === 'DELIVERED' ? 'Entregado al cliente' : 'Devuelto/Retirado'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-3 text-right font-bold text-slate-900">
                                                    {cm.quantity} unid.
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

            {/* TAB 3: Agreements */}
            {activeTab === 'agreements' && (
                <div className="bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                    <div className="p-4 sm:px-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="text-base font-bold font-display text-slate-900">
                                Acuerdos de Precios Especiales
                            </h3>
                            <p className="text-xs text-slate-500">
                                Listas de precios pactadas para este cliente sobre el catálogo general.
                            </p>
                        </div>
                    </div>

                    <div className="p-6">
                        {customer.agreements && customer.agreements.length > 0 ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                {customer.agreements.map((ag) => (
                                    <div
                                        key={ag.id}
                                        className="p-4 rounded-lg border border-slate-200 bg-slate-50/50 flex items-center justify-between"
                                    >
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                                Producto
                                            </p>
                                            <p className="text-sm font-bold text-slate-900 mt-0.5">
                                                {ag.product?.name || 'Producto'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                                Precio Pactado
                                            </p>
                                            <p className="text-lg font-bold font-display text-blue-600 mt-0.5">
                                                ${Number(ag.special_price).toLocaleString('es-AR')}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-slate-400 text-xs">
                                No hay acuerdos de precios diferenciales; rige el precio de lista estándar.
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
