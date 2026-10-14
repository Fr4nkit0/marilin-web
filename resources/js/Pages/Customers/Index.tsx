import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, DebtBadge } from '@/Components/StitchUi';
import {
    Search,
    Plus,
    Eye,
    MapPin,
    Phone,
    Package,
    SlidersHorizontal,
    X,
    Building2,
    Save,
} from 'lucide-react';

interface CustomerAddress {
    id: number;
    street: string;
    apartment?: string;
    floor?: number;
    visual_reference?: string;
    is_primary: boolean;
}

interface ContainerBalance {
    id: number;
    balance: number;
    container_type?: {
        name: string;
        code: string;
    };
}

interface CustomerItem {
    id: number;
    name: string;
    phone_number?: string;
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
    primary_address?: CustomerAddress;
    balance?: {
        current_balance: string | number;
    };
    container_balances?: ContainerBalance[];
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    customers: PaginatedData<CustomerItem>;
    filters: {
        search?: string;
        repartidor_id?: string;
        debt_status?: string;
    };
    repartidores: { id: number; name: string }[];
}

export default function CustomersIndex({ customers, filters, repartidores }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [repartidorId, setRepartidorId] = useState(filters.repartidor_id || '');
    const [debtStatus, setDebtStatus] = useState(filters.debt_status || 'all');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<CustomerItem | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        phone_number: '',
        repartidor_id: '',
        street: '',
        apartment: '',
        visual_reference: '',
        initial_balance: '0',
        bidon_20l_balance: '0',
        sifon_2l_balance: '0',
    });

    const editForm = useForm({
        name: '',
        phone_number: '',
        repartidor_id: '',
        street: '',
        apartment: '',
        visual_reference: '',
    });

    const handleCreateCustomer = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('customers.store'), {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                reset();
            },
        });
    };

    const handleOpenEditModal = (c: CustomerItem) => {
        setEditingCustomer(c);
        const assignedDriverId = c.driver?.id ?? c.repartidor?.id;
        editForm.setData({
            name: c.name,
            phone_number: c.phone_number || '',
            repartidor_id: assignedDriverId ? String(assignedDriverId) : '',
            street: c.primary_address?.street || '',
            apartment: c.primary_address?.apartment || '',
            visual_reference: c.primary_address?.visual_reference || '',
        });
    };

    const handleUpdateCustomer = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCustomer) return;
        editForm.put(route('customers.update', editingCustomer.id), {
            onSuccess: () => {
                setEditingCustomer(null);
            },
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            route('customers.index'),
            {
                search,
                repartidor_id: repartidorId || undefined,
                debt_status: debtStatus !== 'all' ? debtStatus : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    const handleFilterChange = (key: string, value: string) => {
        const newFilters = {
            search,
            repartidor_id: key === 'repartidor_id' ? value : repartidorId,
            debt_status: key === 'debt_status' ? value : debtStatus,
        };

        if (key === 'repartidor_id') setRepartidorId(value);
        if (key === 'debt_status') setDebtStatus(value);

        router.get(
            route('customers.index'),
            {
                search: newFilters.search || undefined,
                repartidor_id: newFilters.repartidor_id || undefined,
                debt_status: newFilters.debt_status !== 'all' ? newFilters.debt_status : undefined,
            },
            { preserveState: true, replace: true }
        );
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-slate-500">
                        Directorio
                    </span>
                </div>
            }
        >
            <Head title="Clientes - Marilin" />

            <div className="mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">
                        Gestión de Clientes
                    </h1>
                    <p className="text-sm font-medium text-slate-500 mt-1 tracking-wide">
                        Total de {customers.total} clientes registrados en el sistema.
                    </p>
                </div>
                <button
                    onClick={() => setIsCreateModalOpen(true)}
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition shadow-sm cursor-pointer"
                >
                    <Plus className="w-4 h-4" />
                    Nuevo Cliente
                </button>
            </div>

            {/* Filters Bar */}
            <div className="bg-white/80 backdrop-blur-md rounded-2xl p-4 mb-6 shadow-sm border border-gray-200/60">
                <form onSubmit={handleSearch} className="grid grid-cols-1 gap-4 sm:grid-cols-12 items-center">
                    {/* Search Input */}
                    <div className="sm:col-span-6 relative">
                        <Search className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre, teléfono o dirección..."
                            className="w-full pl-9 pr-4 py-2 text-sm border border-gray-200/60 bg-white text-slate-900 placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 font-medium"
                        />
                    </div>

                    {/* Repartidor Filter */}
                    <div className="sm:col-span-3">
                        <select
                            value={repartidorId}
                            onChange={(e) => handleFilterChange('repartidor_id', e.target.value)}
                            className="w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 font-medium"
                        >
                            <option value="">Todos los Repartidores</option>
                            {repartidores.map((rep) => (
                                <option key={rep.id} value={rep.id}>
                                    {rep.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Debt Filter */}
                    <div className="sm:col-span-3">
                        <select
                            value={debtStatus}
                            onChange={(e) => handleFilterChange('debt_status', e.target.value)}
                            className="w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 placeholder-gray-400 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 font-medium"
                        >
                            <option value="all">Todos los Estados</option>
                            <option value="with_debt">Con Deuda Pendiente</option>
                            <option value="clean">Al Día (Sin Deuda)</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Customers Table */}
            <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                        <thead className="bg-white/80 backdrop-blur-md border-b border-gray-100 text-slate-500">
                            <tr>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Cliente</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Dirección</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Repartidor</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide text-center">Envases</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide text-right">Cuenta Corriente</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="">
                            {customers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-10 text-center text-slate-500 text-sm">
                                        No se encontraron clientes con los filtros aplicados.
                                    </td>
                                </tr>
                            ) : (
                                customers.data.map((c) => {
                                    const balanceNum = Number(c.balance?.current_balance || 0);
                                    const hasDebt = balanceNum > 0;
                                    const totalContainers = c.container_balances?.reduce(
                                        (acc, cb) => acc + cb.balance,
                                        0
                                    ) || 0;

                                    return (
                                        <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                                            {/* Name & Phone */}
                                            <td className="px-6 py-4">
                                                <div className="font-medium text-slate-900">
                                                    {c.name}
                                                </div>
                                                {c.phone_number && (
                                                    <div className="flex items-center gap-1.5 text-slate-500 text-xs mt-1">
                                                        <Phone className="w-3.5 h-3.5" />
                                                        <span>{c.phone_number}</span>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Primary Address */}
                                            <td className="px-6 py-4 text-gray-600">
                                                {c.primary_address ? (
                                                    <div className="flex items-start gap-2 max-w-xs">
                                                        <MapPin className="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
                                                        <span className="truncate whitespace-normal">
                                                            {c.primary_address.street}
                                                            {c.primary_address.apartment && (
                                                                <span className="text-gray-400">
                                                                    , Depto {c.primary_address.apartment}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-gray-400 italic">Sin dirección</span>
                                                )}
                                            </td>

                                            {/* Repartidor / Driver */}
                                            <td className="px-6 py-4">
                                                {(c.driver?.user || c.repartidor?.user) ? (
                                                    <span className="inline-flex items-center gap-2 font-medium text-slate-900">
                                                        <div className="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-xs">
                                                            {(c.driver?.user?.name || c.repartidor?.user?.name || '').charAt(0)}
                                                        </div>
                                                        {c.driver?.user?.name || c.repartidor?.user?.name}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-gray-600">Sin asignar</span>
                                                )}
                                            </td>

                                            {/* Containers Balance */}
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 text-gray-700 font-medium text-xs">
                                                    <Package className="w-3.5 h-3.5" />
                                                    {totalContainers} unid
                                                </span>
                                            </td>

                                            {/* Debt Status & Balance */}
                                            <td className="px-6 py-4 text-right">
                                                <DebtBadge hasDebt={hasDebt} balance={balanceNum} />
                                            </td>

                                            {/* Actions */}
                                            <td className="px-6 py-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenEditModal(c)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-full border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-medium transition cursor-pointer text-xs shadow-sm"
                                                        title="Editar datos del cliente"
                                                    >
                                                        <SlidersHorizontal className="w-3.5 h-3.5" />
                                                        Editar
                                                    </button>
                                                    <Link
                                                        href={route('customers.show', c.id)}
                                                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-blue-600 text-white hover:bg-blue-700 font-medium transition text-xs shadow-sm"
                                                    >
                                                        <Eye className="w-3.5 h-3.5" />
                                                        Ver Ficha
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {customers.last_page > 1 && (
                    <div className="px-6 py-4 border-t border-gray-200 bg-white flex items-center justify-between text-sm font-medium text-slate-500 tracking-wide">
                        <span>
                            Página {customers.current_page} de {customers.last_page}
                        </span>
                        <div className="flex items-center gap-1">
                            {customers.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveState
                                    className={`px-3 py-2 rounded-lg text-sm font-medium transition ${
                                        link.active
                                            ? 'bg-slate-100 text-slate-900'
                                            : !link.url
                                            ? 'text-gray-400 pointer-events-none'
                                            : 'text-gray-600 hover:bg-gray-50'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal para Crear Nuevo Cliente */}
            {isCreateModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
                    {/* Backdrop */}
                    <div
                        className="fixed inset-0 bg-gray-900/80 backdrop-blur-none transition-opacity"
                        onClick={() => setIsCreateModalOpen(false)}
                    />

                    {/* Modal Card */}
                    <div className="relative bg-white rounded-2xl shadow-xl border border-gray-200/60 max-w-lg w-full overflow-hidden z-10 my-8">
                        <div className="bg-white/80 backdrop-blur-md border-b border-gray-100 p-5 text-slate-900 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center">
                                    <Building2 className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-semibold text-slate-900 tracking-wide">
                                        Alta de Nuevo Cliente
                                    </h3>
                                    <p className="text-sm font-medium text-slate-500 tracking-wide">
                                        Ingresa los datos generales, dirección y condiciones iniciales.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setIsCreateModalOpen(false)}
                                className="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateCustomer} className="p-6 space-y-5">
                            {/* Nombre o Razón Social */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Nombre o Razón Social <span className="text-slate-900">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Ej: Sanatorio Los Lapachos S.A."
                                    className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                />
                                {errors.name && <p className="text-sm text-red-600 mt-1">{errors.name}</p>}
                            </div>

                            {/* Teléfono & Repartidor */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Teléfono de Contacto
                                    </label>
                                    <input
                                        type="text"
                                        value={data.phone_number}
                                        onChange={(e) => setData('phone_number', e.target.value)}
                                        placeholder="Ej: +54 388 422-3000"
                                        className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                    />
                                    {errors.phone_number && (
                                        <p className="text-sm text-red-600 mt-1">{errors.phone_number}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Repartidor Asignado
                                    </label>
                                    <select
                                        value={data.repartidor_id}
                                        onChange={(e) => setData('repartidor_id', e.target.value)}
                                        className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600  bg-white"
                                    >
                                        <option value="">Sin Asignar</option>
                                        {repartidores.map((rep) => (
                                            <option key={rep.id} value={rep.id}>
                                                {rep.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.repartidor_id && (
                                        <p className="text-sm text-red-600 mt-1">{errors.repartidor_id}</p>
                                    )}
                                </div>
                            </div>

                            {/* Dirección */}
                            <div className="space-y-4 pt-5 border-t border-gray-100">
                                <h4 className="text-sm font-semibold text-slate-900">
                                    Dirección Principal
                                </h4>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Calle y Altura <span className="text-slate-900">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={data.street}
                                        onChange={(e) => setData('street', e.target.value)}
                                        placeholder="Ej: Belgrano 340"
                                        className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                    />
                                    {errors.street && <p className="text-sm text-red-600 mt-1">{errors.street}</p>}
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Piso / Departamento
                                        </label>
                                        <input
                                            type="text"
                                            value={data.apartment}
                                            onChange={(e) => setData('apartment', e.target.value)}
                                            placeholder="Ej: Piso 2 - Depto B"
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Referencia Visual
                                        </label>
                                        <input
                                            type="text"
                                            value={data.visual_reference}
                                            onChange={(e) => setData('visual_reference', e.target.value)}
                                            placeholder="Ej: Frente a Plaza Belgrano"
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Saldos Iniciales & Envases Comodato */}
                            <div className="space-y-4 pt-5 border-t border-gray-100">
                                <h4 className="text-sm font-semibold text-slate-900">
                                    Condiciones Iniciales
                                </h4>

                                <div className="grid grid-cols-3 gap-5">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Deuda Inicial ($)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.initial_balance}
                                            onChange={(e) => setData('initial_balance', e.target.value)}
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600  font-medium"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Bidones 20L
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            value={data.bidon_20l_balance}
                                            onChange={(e) => setData('bidon_20l_balance', e.target.value)}
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600  font-medium"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Sifones 2L
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            value={data.sifon_2l_balance}
                                            onChange={(e) => setData('sifon_2l_balance', e.target.value)}
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600  font-medium"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Buttons */}
                            <div className="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                                <button
                                    type="button"
                                    onClick={() => setIsCreateModalOpen(false)}
                                    className="px-4 py-2 rounded-lg bg-slate-200 border-none text-sm font-medium text-slate-800 hover:bg-slate-300 transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition shadow-sm cursor-pointer flex items-center gap-2 disabled:opacity-50"
                                >
                                    <Save className="w-4 h-4" />
                                    {processing ? 'Guardando...' : 'Guardar Cliente'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal para Editar Cliente Existente */}
            {editingCustomer && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
                    {/* Backdrop */}
                    <div
                        className="fixed inset-0 bg-gray-900/80 backdrop-blur-none transition-opacity"
                        onClick={() => setEditingCustomer(null)}
                    />

                    {/* Modal Card */}
                    <div className="relative bg-white rounded-2xl shadow-xl border border-gray-200/60 max-w-lg w-full overflow-hidden z-10 my-8">
                        <div className="bg-white/80 backdrop-blur-md border-b border-gray-100 p-5 text-slate-900 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-lg bg-slate-100 text-slate-900 flex items-center justify-center">
                                    <SlidersHorizontal className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-semibold text-slate-900 tracking-wide">
                                        Editar Datos de Cliente
                                    </h3>
                                    <p className="text-sm font-medium text-slate-500 tracking-wide">
                                        Actualiza nombre, teléfono, repartidor o dirección física.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setEditingCustomer(null)}
                                className="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleUpdateCustomer} className="p-6 space-y-5">
                            {/* Nombre o Razón Social */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                    Nombre o Razón Social <span className="text-slate-900">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={editForm.data.name}
                                    onChange={(e) => editForm.setData('name', e.target.value)}
                                    className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                />
                                {editForm.errors.name && (
                                    <p className="text-sm text-red-600 mt-1">{editForm.errors.name}</p>
                                )}
                            </div>

                            {/* Teléfono & Repartidor */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Teléfono de Contacto
                                    </label>
                                    <input
                                        type="text"
                                        value={editForm.data.phone_number}
                                        onChange={(e) => editForm.setData('phone_number', e.target.value)}
                                        placeholder="Ej: +54 388 422-3000"
                                        className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                    />
                                    {editForm.errors.phone_number && (
                                        <p className="text-sm text-red-600 mt-1">{editForm.errors.phone_number}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Repartidor Asignado
                                    </label>
                                    <select
                                        value={editForm.data.repartidor_id}
                                        onChange={(e) => editForm.setData('repartidor_id', e.target.value)}
                                        className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600  bg-white"
                                    >
                                        <option value="">Sin Asignar</option>
                                        {repartidores.map((rep) => (
                                            <option key={rep.id} value={rep.id}>
                                                {rep.name}
                                            </option>
                                        ))}
                                    </select>
                                    {editForm.errors.repartidor_id && (
                                        <p className="text-sm text-red-600 mt-1">{editForm.errors.repartidor_id}</p>
                                    )}
                                </div>
                            </div>

                            {/* Dirección */}
                            <div className="space-y-4 pt-5 border-t border-gray-100">
                                <h4 className="text-sm font-semibold text-slate-900">
                                    Dirección Principal
                                </h4>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                        Calle y Altura <span className="text-slate-900">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={editForm.data.street}
                                        onChange={(e) => editForm.setData('street', e.target.value)}
                                        className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                    />
                                    {editForm.errors.street && (
                                        <p className="text-sm text-red-600 mt-1">{editForm.errors.street}</p>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Piso / Departamento
                                        </label>
                                        <input
                                            type="text"
                                            value={editForm.data.apartment}
                                            onChange={(e) => editForm.setData('apartment', e.target.value)}
                                            placeholder="Ej: Depto 4B"
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1.5">
                                            Referencia Visual
                                        </label>
                                        <input
                                            type="text"
                                            value={editForm.data.visual_reference}
                                            onChange={(e) => editForm.setData('visual_reference', e.target.value)}
                                            placeholder="Ej: Frente a Plaza Belgrano"
                                            className="w-full px-4 py-2 text-sm border border-gray-200/60 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 "
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Buttons */}
                            <div className="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                                <button
                                    type="button"
                                    onClick={() => setEditingCustomer(null)}
                                    className="px-4 py-2 rounded-lg bg-slate-200 border-none text-sm font-medium text-slate-800 hover:bg-slate-300 transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition shadow-sm cursor-pointer flex items-center gap-2 disabled:opacity-50"
                                >
                                    <Save className="w-4 h-4" />
                                    {editForm.processing ? 'Guardando...' : 'Guardar Cambios'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
