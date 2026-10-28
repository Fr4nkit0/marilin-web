import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { DebtBadge, PaymentMethodBadge } from '@/ui/Badge';
import { PageHeader } from '@/ui/PageHeader';
import CustomerAgreementFormModal from '@/Components/CustomerAgreementFormModal';
import HomePaymentModal from '@/Components/HomePaymentModal';
import HomeSaleModal from '@/Components/HomeSaleModal';
import { HomeContainerType, HomePaymentMethod, HomeProduct } from '@/types/home-operation';
import { AgreementProduct, AgreementStatus, CustomerAgreementRow } from '@/types/agreement';
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
import { money } from '@/lib/format';

interface Props {
    customer: {
        id: number;
        name: string;
        phone_number?: string;
        primary_address?: {
            street: string;
            apartment?: string;
            floor?: string;
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
    agreements: CustomerAgreementRow[];
    agreementProducts: AgreementProduct[];
    canManageAgreements: boolean;
    canRecordAtHome: boolean;
    homeProducts: HomeProduct[];
    paymentMethods: HomePaymentMethod[];
    containerTypes: HomeContainerType[];
}

const AGREEMENT_STATUS: Record<AgreementStatus, { label: string; className: string }> = {
    active: { label: 'Vigente', className: 'bg-emerald-50 text-emerald-700' },
    upcoming: { label: 'Futuro', className: 'bg-indigo-50 text-indigo-700' },
    ended: { label: 'Finalizado', className: 'bg-slate-100 text-slate-500' },
};

const formatDay = (day: string) => day.split('-').reverse().join('/');

const localToday = () => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

export default function CustomerShow({
    customer,
    accountEntries,
    containerMovements,
    agreements,
    agreementProducts,
    canManageAgreements,
    canRecordAtHome,
    homeProducts,
    paymentMethods,
    containerTypes,
}: Props) {
    const [activeTab, setActiveTab] = useState<'account' | 'containers' | 'agreements'>('account');
    const [showAgreementForm, setShowAgreementForm] = useState(false);
    const [showHomeSale, setShowHomeSale] = useState(false);
    const [showHomePayment, setShowHomePayment] = useState(false);
    const pageErrors = usePage().props.errors as Record<string, string>;

    const endAgreement = (agreement: CustomerAgreementRow) => {
        const confirmed = window.confirm(
            `¿Finalizar el acuerdo de ${agreement.product ?? 'este producto'}? Deja de aplicarse desde hoy.`,
        );
        if (!confirmed) return;
        router.patch(route('customers.agreements.end', [customer.id, agreement.id]), {}, { preserveScroll: true });
    };

    const balanceNum = Number(customer.balance?.current_balance || 0);
    const hasDebt = balanceNum > 0;

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Link
                        href={route('customers.index')}
                        className="text-slate-500 hover:text-slate-900 transition-colors"
                    >
                        Clientes
                    </Link>
                    <span className="text-slate-400">/</span>
                    <span className="text-slate-900">{customer.name}</span>
                </div>
            }
        >
            <Head title={`${customer.name} - Detalle de Cliente`} />

            {/* Back button */}
            <div className="mb-4">
                <Link
                    href={route('customers.index')}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-900 transition"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Volver al listado
                </Link>
            </div>

            {/* Top Customer 360 Header Card */}
            <div className="bg-white/80 backdrop-blur-md rounded-2xl p-6 mb-6 shadow-sm border border-gray-200/60 relative overflow-hidden">
                {/* Decorative background subtle gradient or color block if needed, but keeping it white and clean */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6 relative z-10">
                    <div className="flex items-start gap-5">
                        <div className="w-16 h-16 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center text-2xl font-medium">
                            {customer.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">
                                    {customer.name}
                                </h1>
                                <DebtBadge hasDebt={hasDebt} balance={balanceNum} />
                            </div>
                            <div className="flex flex-wrap items-center gap-5 text-sm font-medium text-slate-500 mt-2 tracking-wide">
                                {customer.phone_number && (
                                    <div className="flex items-center gap-1.5">
                                        <Phone className="w-4 h-4 text-slate-400" />
                                        <span>{customer.phone_number}</span>
                                    </div>
                                )}
                                {customer.primary_address && (
                                    <div className="flex items-center gap-1.5">
                                        <MapPin className="w-4 h-4 text-slate-400" />
                                        <span>
                                            {customer.primary_address.street}
                                            {customer.primary_address.floor && ` (Piso ${customer.primary_address.floor})`}
                                            {customer.primary_address.apartment && ` (Depto ${customer.primary_address.apartment})`}
                                            {customer.primary_address.visual_reference && ` - ${customer.primary_address.visual_reference}`}
                                        </span>
                                    </div>
                                )}
                                {(customer.driver?.user || customer.repartidor?.user) && (
                                    <div className="flex items-center gap-1.5">
                                        <Truck className="w-4 h-4 text-slate-400" />
                                        <span>
                                            Repartidor: <strong className="text-slate-900 font-medium">{customer.driver?.user?.name || customer.repartidor?.user?.name}</strong>
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Quick Stats on Right */}
                    <div className="flex items-center gap-4 border-t border-slate-700 md:border-t-0 md:border-l border-slate-700 md:pl-6 pt-4 md:pt-0">
                        <div className="text-right">
                            <span className="text-xs font-medium text-slate-400 block mb-1 tracking-wide">
                                Saldo Actual
                            </span>
                            <p className={`text-3xl font-semibold tracking-tight ${hasDebt ? 'text-red-600' : 'text-emerald-600'}`}>
                                {money(Math.abs(balanceNum))}
                            </p>
                            <span className="text-xs font-medium text-slate-400 mt-1 block tracking-wide">
                                {hasDebt ? 'Deuda pendiente' : 'Al día / Sin saldo'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {canRecordAtHome && (
                <div className="flex flex-wrap items-center gap-3 mb-6">
                    <button
                        type="button"
                        onClick={() => setShowHomeSale(true)}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 cursor-pointer"
                    >
                        <Plus className="w-4 h-4" />
                        Venta en casa
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowHomePayment(true)}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-700 bg-white ring-1 ring-inset ring-slate-200/80 hover:bg-slate-50 cursor-pointer"
                    >
                        <CircleDollarSign className="w-4 h-4" />
                        Cobro en casa
                    </button>
                </div>
            )}

            {/* Content Tabs Navigation */}
            <div className="flex items-center gap-6 mb-6 border-b border-gray-100">
                <button
                    onClick={() => setActiveTab('account')}
                    className={`pb-3 text-sm font-medium tracking-wide transition-colors border-b-4 ${
                        activeTab === 'account'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-gray-200/60'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <Receipt className="w-4 h-4" />
                        Cuenta Corriente
                    </span>
                </button>
                <button
                    onClick={() => setActiveTab('containers')}
                    className={`pb-3 text-sm font-medium tracking-wide transition-colors border-b-4 ${
                        activeTab === 'containers'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-gray-200/60'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <Package className="w-4 h-4" />
                        Envases Retornables
                    </span>
                </button>
                <button
                    onClick={() => setActiveTab('agreements')}
                    className={`pb-3 text-sm font-medium tracking-wide transition-colors border-b-4 ${
                        activeTab === 'agreements'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-gray-200/60'
                    }`}
                >
                    <span className="flex items-center gap-2">
                        <Tag className="w-4 h-4" />
                        Acuerdos
                    </span>
                </button>
            </div>

            {/* TAB 1: Account Entries */}
            {activeTab === 'account' && (
                <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                    <div className="p-5 border-b border-gray-100 bg-white">
                        <h3 className="text-base font-semibold text-slate-900 tracking-wide">
                            Movimientos Contables
                        </h3>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide">Fecha y Hora</th>
                                    <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide">Concepto</th>
                                    <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide text-center">Tipo</th>
                                    <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide text-center">Método</th>
                                    <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white">
                                {accountEntries.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-8 text-center text-slate-500 text-sm">
                                            No hay movimientos registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    accountEntries.map((entry) => {
                                        const isDebit = entry.direction === 1; // Sale / increase debt
                                        return (
                                            <tr key={entry.id} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-6 py-4 text-gray-600">
                                                    {entry.createdAt}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-slate-900">
                                                    {entry.description || (isDebit ? 'Venta de productos' : 'Cobranza')}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span
                                                        className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                                                            isDebit
                                                                ? 'bg-slate-100 text-slate-700'
                                                                : 'bg-slate-100 text-gray-700'
                                                        }`}
                                                    >
                                                        {entry.entryType}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    {entry.paymentMethod ? (
                                                        <PaymentMethodBadge method={entry.paymentMethod} />
                                                    ) : (
                                                        <span className="text-slate-400">-</span>
                                                    )}
                                                </td>
                                                <td
                                                    className={`px-6 py-4 text-right font-medium text-sm ${
                                                        isDebit ? 'text-red-600' : 'text-emerald-600'
                                                    }`}
                                                >
                                                    {isDebit ? '+' : '-'}{money(entry.amount)}
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
                        <h3 className="text-sm font-semibold text-slate-900 mb-3 border-b border-gray-100 pb-2">
                            Envases en Posesión
                        </h3>
                        {customer.container_balances?.map((cb) => (
                            <div
                                key={cb.id}
                                className="bg-white rounded-lg border border-gray-200 p-5 shadow-sm flex items-center justify-between"
                            >
                                <div className="flex items-center gap-4">
                                    <div className="p-2.5 rounded-lg bg-slate-100 text-slate-900">
                                        <Package className="w-6 h-6" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">
                                            {cb.container_type?.name || 'Envase'}
                                        </p>
                                        <p className="text-xs text-slate-500 mt-0.5">
                                            Capacidad: {cb.container_type?.capacity_liters || 20}L
                                        </p>
                                    </div>
                                </div>
                                <span className="text-2xl font-medium text-slate-900">
                                    {cb.balance}
                                </span>
                            </div>
                        ))}
                        {(!customer.container_balances || customer.container_balances.length === 0) && (
                            <p className="text-sm text-slate-500 italic">El cliente no posee envases en comodato.</p>
                        )}
                    </div>

                    {/* Movement History */}
                    <div className="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                        <div className="p-5 border-b border-gray-100">
                            <h3 className="text-base font-semibold text-slate-900 tracking-wide">
                                Historial de Envases
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm whitespace-nowrap">
                                <thead className="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide">Fecha</th>
                                        <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide">Envase</th>
                                        <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide text-center">Operación</th>
                                        <th className="px-6 py-4 text-xs font-medium text-slate-500 tracking-wide text-right">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white">
                                    {containerMovements.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-6 py-8 text-center text-slate-500 text-sm">
                                                Sin movimientos recientes.
                                            </td>
                                        </tr>
                                    ) : (
                                        containerMovements.map((cm) => (
                                            <tr key={cm.id} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-6 py-4 text-gray-600">
                                                    {cm.created_at}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-slate-900">
                                                    {cm.container_type?.name}
                                                </td>
                                                <td className="px-6 py-4 text-center">
                                                    <span
                                                        className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                                                            cm.type === 'DELIVERED'
                                                                ? 'bg-slate-100 text-slate-700'
                                                                : 'bg-slate-100 text-gray-700'
                                                        }`}
                                                    >
                                                        {cm.type === 'DELIVERED' ? 'Entregado' : 'Devuelto'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-right font-medium text-slate-900">
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
                <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                    <div className="p-5 border-b border-gray-100 flex items-center justify-between gap-4">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900 tracking-wide">
                                Acuerdos de Precios
                            </h3>
                            <p className="text-xs text-slate-500 mt-1">
                                Para cambiar un precio, finalizá el acuerdo y creá uno nuevo: las ventas ya hechas
                                conservan el precio que usaron.
                            </p>
                        </div>
                        {canManageAgreements && (
                            <button
                                type="button"
                                onClick={() => setShowAgreementForm(true)}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 cursor-pointer whitespace-nowrap"
                            >
                                <Plus className="w-4 h-4" />
                                Nuevo acuerdo
                            </button>
                        )}
                    </div>

                    {pageErrors.agreement && (
                        <p className="mx-5 mt-4 text-sm text-red-600">{pageErrors.agreement}</p>
                    )}

                    {agreements.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs font-medium text-slate-500 border-b border-gray-100">
                                        <th className="px-5 py-3">Producto</th>
                                        <th className="px-5 py-3 text-right">Precio acordado</th>
                                        <th className="px-5 py-3">Vigencia</th>
                                        <th className="px-5 py-3">Estado</th>
                                        <th className="px-5 py-3">Motivo</th>
                                        <th className="px-5 py-3">Creado por</th>
                                        {canManageAgreements && <th className="px-5 py-3" />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {agreements.map((agreement) => (
                                        <tr key={agreement.id} className="border-b border-gray-50 last:border-0">
                                            <td className="px-5 py-3 font-medium text-slate-900">
                                                {agreement.product ?? 'Producto'}
                                            </td>
                                            <td className="px-5 py-3 text-right font-semibold text-slate-900">
                                                {money(agreement.unitPrice)}
                                            </td>
                                            <td className="px-5 py-3 text-slate-600 whitespace-nowrap">
                                                {formatDay(agreement.validFrom)} →{' '}
                                                {agreement.validTo ? formatDay(agreement.validTo) : 'sin fin'}
                                            </td>
                                            <td className="px-5 py-3">
                                                <span
                                                    className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${AGREEMENT_STATUS[agreement.status].className}`}
                                                >
                                                    {AGREEMENT_STATUS[agreement.status].label}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-slate-600">{agreement.reason ?? '—'}</td>
                                            <td className="px-5 py-3 text-slate-600">{agreement.createdBy ?? '—'}</td>
                                            {canManageAgreements && (
                                                <td className="px-5 py-3 text-right">
                                                    {agreement.status !== 'ended' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => endAgreement(agreement)}
                                                            className="text-sm font-medium text-red-600 hover:text-red-500 cursor-pointer"
                                                        >
                                                            Finalizar
                                                        </button>
                                                    )}
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="m-6 text-center py-12 text-slate-500 text-sm border-2 border-dashed border-gray-200 rounded-lg">
                            Sin acuerdos especiales.
                        </div>
                    )}
                </div>
            )}

            {canRecordAtHome && (
                <>
                    <HomeSaleModal
                        show={showHomeSale}
                        customerId={customer.id}
                        customerName={customer.name}
                        products={homeProducts}
                        containerTypes={containerTypes}
                        onClose={() => setShowHomeSale(false)}
                    />
                    <HomePaymentModal
                        show={showHomePayment}
                        customerId={customer.id}
                        customerName={customer.name}
                        paymentMethods={paymentMethods}
                        onClose={() => setShowHomePayment(false)}
                    />
                </>
            )}

            <CustomerAgreementFormModal
                show={showAgreementForm}
                customerId={customer.id}
                customerName={customer.name}
                products={agreementProducts}
                today={localToday()}
                onClose={() => setShowAgreementForm(false)}
            />
        </AdminLayout>
    );
}
