import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageHeader, DebtBadge } from '@/Components/StitchUi';
import {
    Search,
    Filter,
    Plus,
    Eye,
    MapPin,
    Phone,
    User,
    Package,
    ChevronLeft,
    ChevronRight,
    SlidersHorizontal,
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
                    <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                        Directorio
                    </span>
                </div>
            }
        >
            <Head title="Clientes - Marilin" />

            <PageHeader
                title="Gestión de Clientes"
                description={`Total de ${customers.total} clientes registrados en el sistema.`}
                actions={
                    <button className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 transition shadow-xs">
                        <Plus className="w-4 h-4" />
                        Nuevo Cliente
                    </button>
                }
            />

            {/* Filters Bar */}
            <div className="bg-white rounded-lg border border-slate-200 p-4 mb-6 shadow-2xs">
                <form onSubmit={handleSearch} className="grid grid-cols-1 gap-3 sm:grid-cols-12 items-center">
                    {/* Search Input */}
                    <div className="sm:col-span-6 relative">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nombre, teléfono o dirección..."
                            className="w-full pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        />
                    </div>

                    {/* Repartidor Filter */}
                    <div className="sm:col-span-3">
                        <select
                            value={repartidorId}
                            onChange={(e) => handleFilterChange('repartidor_id', e.target.value)}
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
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
                            className="w-full py-2 px-3 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 bg-white"
                        >
                            <option value="all">Todos los Estados</option>
                            <option value="with_debt">Con Deuda Pendiente</option>
                            <option value="clean">Al Día (Sin Deuda)</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Customers Table */}
            <div className="bg-white rounded-lg border border-slate-200 shadow-2xs overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 font-semibold border-b border-slate-100 uppercase tracking-wider">
                            <tr>
                                <th className="px-6 py-3.5">Cliente</th>
                                <th className="px-6 py-3.5">Dirección</th>
                                <th className="px-6 py-3.5">Repartidor</th>
                                <th className="px-6 py-3.5 text-center">Envases</th>
                                <th className="px-6 py-3.5 text-right">Cuenta Corriente</th>
                                <th className="px-6 py-3.5 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {customers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-10 text-center text-slate-400">
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
                                        <tr key={c.id} className="hover:bg-slate-50/80 transition">
                                            {/* Name & Phone */}
                                            <td className="px-6 py-4">
                                                <div className="font-semibold text-slate-900 text-sm">
                                                    {c.name}
                                                </div>
                                                {c.phone_number && (
                                                    <div className="flex items-center gap-1 text-slate-500 text-[11px] mt-0.5">
                                                        <Phone className="w-3 h-3 text-slate-400" />
                                                        <span>{c.phone_number}</span>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Primary Address */}
                                            <td className="px-6 py-4 text-slate-600">
                                                {c.primary_address ? (
                                                    <div className="flex items-start gap-1.5 max-w-xs">
                                                        <MapPin className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                                                        <span className="truncate">
                                                            {c.primary_address.street}
                                                            {c.primary_address.apartment && (
                                                                <span className="text-slate-400">
                                                                    , Depto {c.primary_address.apartment}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 italic">Sin dirección</span>
                                                )}
                                            </td>

                                            {/* Repartidor */}
                                            <td className="px-6 py-4">
                                                {c.repartidor?.user ? (
                                                    <span className="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                                        <div className="w-5 h-5 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-[10px] font-bold">
                                                            {c.repartidor.user.name.charAt(0)}
                                                        </div>
                                                        {c.repartidor.user.name}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 italic">Sin asignar</span>
                                                )}
                                            </td>

                                            {/* Containers Balance */}
                                            <td className="px-6 py-4 text-center">
                                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-medium text-[11px]">
                                                    <Package className="w-3 h-3 text-slate-500" />
                                                    {totalContainers} unid.
                                                </span>
                                            </td>

                                            {/* Debt Status & Balance */}
                                            <td className="px-6 py-4 text-right">
                                                <DebtBadge hasDebt={hasDebt} balance={balanceNum} />
                                            </td>

                                            {/* Actions */}
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={route('customers.show', c.id)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border border-slate-200 text-slate-700 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 font-medium transition"
                                                >
                                                    <Eye className="w-3.5 h-3.5" />
                                                    Ver Ficha
                                                </Link>
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
                    <div className="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>
                            Mostrando página {customers.current_page} de {customers.last_page}
                        </span>
                        <div className="flex items-center gap-1">
                            {customers.links.map((link, idx) => (
                                <Link
                                    key={idx}
                                    href={link.url || '#'}
                                    preserveState
                                    className={`px-3 py-1.5 rounded-md border text-xs font-medium transition ${
                                        link.active
                                            ? 'bg-blue-600 text-white border-blue-600 font-bold'
                                            : !link.url
                                            ? 'text-slate-300 border-slate-100 pointer-events-none'
                                            : 'text-slate-700 border-slate-200 hover:bg-slate-50'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
