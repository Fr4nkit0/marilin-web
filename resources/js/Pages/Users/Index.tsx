import React, { FormEvent, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useListFilters } from '@/lib/useListFilters';
import { KeyRound, Pencil, Plus, Power, Search } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import DriverAccountFormModal from '@/Components/DriverAccountFormModal';
import DriverPasswordModal from '@/Components/DriverPasswordModal';
import DriverStatusModal from '@/Components/DriverStatusModal';
import { DriverAccount, DriverAccountStatusFilter } from '@/types/user-account';

interface Props {
    users: DriverAccount[];
    filters: { search: string; status: DriverAccountStatusFilter };
}

const statusOptions: { value: DriverAccountStatusFilter; label: string }[] = [
    { value: null, label: 'Todas' },
    { value: 'enabled', label: 'Habilitadas' },
    { value: 'disabled', label: 'Deshabilitadas' },
];

export default function Index({ users, filters }: Props) {
    const filter = useListFilters(
        'users.index',
        { search: filters.search ?? '', status: filters.status ?? '' },
        { search: '', status: '' },
        { preserveScroll: true },
    );
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<DriverAccount | null>(null);
    const [passwordFor, setPasswordFor] = useState<DriverAccount | null>(null);
    const [statusFor, setStatusFor] = useState<DriverAccount | null>(null);

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        filter.submit();
    };

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (account: DriverAccount) => {
        setEditing(account);
        setFormOpen(true);
    };

    const actionClass =
        'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-100 transition cursor-pointer';

    return (
        <AdminLayout header={<span className="text-sm font-medium text-slate-500">Usuarios</span>}>
            <Head title="Usuarios - Marilin" />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">Usuarios</h1>
                    <p className="text-sm font-medium text-slate-500 mt-1 tracking-wide">
                        Cuentas de los repartidores para entrar a la app.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 cursor-pointer"
                >
                    <Plus className="w-4 h-4" />
                    Nuevo repartidor
                </button>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <form onSubmit={submitSearch} className="relative flex-1">
                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="search"
                        value={filter.values.search}
                        onChange={(e) => filter.draft('search', e.target.value)}
                        placeholder="Buscar por nombre, email o usuario"
                        aria-label="Buscar usuarios"
                        className="w-full py-2 pl-9 pr-3 text-sm border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600"
                    />
                </form>
                <div className="flex gap-1 rounded-lg bg-slate-100 p-1" role="group" aria-label="Filtrar por estado">
                    {statusOptions.map((option) => (
                        <button
                            key={option.label}
                            type="button"
                            onClick={() => filter.set('status', option.value ?? '')}
                            aria-pressed={filters.status === option.value}
                            className={`px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer ${
                                filters.status === option.value
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-900'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-white/80 backdrop-blur-md border-b border-gray-100 text-slate-500">
                            <tr>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Repartidor</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Usuario</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Teléfono</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Estado</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-6 py-10 text-center text-slate-500 text-sm">
                                        No hay cuentas que coincidan.
                                    </td>
                                </tr>
                            ) : (
                                users.map((account) => (
                                    <tr key={account.id} className="border-b border-gray-100 last:border-0">
                                        <td className="px-6 py-4">
                                            <div className={`font-medium ${account.enabled ? 'text-slate-900' : 'text-slate-400'}`}>
                                                {account.name}
                                            </div>
                                            <div className="text-xs text-slate-500">{account.email}</div>
                                        </td>
                                        <td className="px-6 py-4 text-slate-600">{account.username ?? '—'}</td>
                                        <td className="px-6 py-4 text-slate-600">{account.phone_number ?? '—'}</td>
                                        <td className="px-6 py-4">
                                            <span
                                                className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                                    account.enabled
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-slate-100 text-slate-500'
                                                }`}
                                            >
                                                {account.enabled ? 'Habilitada' : 'Deshabilitada'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex justify-end gap-1">
                                                <button type="button" onClick={() => openEdit(account)} className={actionClass}>
                                                    <Pencil className="w-3.5 h-3.5" />
                                                    Editar
                                                </button>
                                                <button type="button" onClick={() => setPasswordFor(account)} className={actionClass}>
                                                    <KeyRound className="w-3.5 h-3.5" />
                                                    Contraseña
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setStatusFor(account)}
                                                    className={`${actionClass} ${account.enabled ? 'text-red-600 hover:bg-red-50' : ''}`}
                                                >
                                                    <Power className="w-3.5 h-3.5" />
                                                    {account.enabled ? 'Deshabilitar' : 'Habilitar'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <DriverAccountFormModal show={formOpen} account={editing} onClose={() => setFormOpen(false)} />
            <DriverPasswordModal account={passwordFor} onClose={() => setPasswordFor(null)} />
            <DriverStatusModal account={statusFor} onClose={() => setStatusFor(null)} />
        </AdminLayout>
    );
}
