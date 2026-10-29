import React, { FormEvent, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useListFilters } from '@/lib/useListFilters';
import { KeyRound, Pencil, Plus, Power, Search } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import DriverAccountFormModal from './components/DriverAccountFormModal';
import DriverPasswordModal from './components/DriverPasswordModal';
import DriverStatusModal from './components/DriverStatusModal';
import { DriverAccount, DriverAccountStatusFilter } from '@/modules/Accounts/types';

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
        'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-ink-secondary hover:bg-surface-1 transition cursor-pointer';

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Usuarios</span>}>
            <Head title="Usuarios - Marilin" />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-ink tracking-tight">Usuarios</h1>
                    <p className="text-sm font-medium text-ink-muted mt-1 tracking-wide">
                        Cuentas de los repartidores para entrar a la app.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={openCreate}
                    className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                >
                    <Plus className="w-4 h-4" />
                    Nuevo repartidor
                </button>
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <form onSubmit={submitSearch} className="relative flex-1">
                    <Search className="w-4 h-4 text-ink-subtle absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="search"
                        value={filter.values.search}
                        onChange={(e) => filter.draft('search', e.target.value)}
                        placeholder="Buscar por nombre, email o usuario"
                        aria-label="Buscar usuarios"
                        className="w-full py-2 pl-9 pr-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600"
                    />
                </form>
                <div className="flex gap-1 rounded-lg bg-surface-1 p-1" role="group" aria-label="Filtrar por estado">
                    {statusOptions.map((option) => (
                        <button
                            key={option.label}
                            type="button"
                            onClick={() => filter.set('status', option.value ?? '')}
                            aria-pressed={filters.status === option.value}
                            className={`px-3 py-1.5 rounded-md text-xs font-medium transition cursor-pointer ${
                                filters.status === option.value
                                    ? 'bg-canvas text-ink shadow-sm'
                                    : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden ">
                <div className="table-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-canvas/80 backdrop-blur-md border-b border-hairline text-ink-muted">
                            <tr>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Repartidor</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Usuario</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Teléfono</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide">Estado</th>
                                <th className="px-6 py-4 text-xs font-medium text-ink tracking-wide text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-6 py-10 text-center text-ink-muted text-sm">
                                        <span className="table-empty">No hay cuentas que coincidan.</span>
                                    </td>
                                </tr>
                            ) : (
                                users.map((account) => (
                                    <tr key={account.id} className="border-b border-hairline last:border-0">
                                        <td className="px-6 py-4">
                                            <div className={`font-medium ${account.enabled ? 'text-ink' : 'text-ink-subtle'}`}>
                                                {account.name}
                                            </div>
                                            <div className="text-xs text-ink-muted">{account.email}</div>
                                        </td>
                                        <td className="px-6 py-4 text-ink-secondary">{account.username ?? '—'}</td>
                                        <td className="px-6 py-4 text-ink-secondary">{account.phone_number ?? '—'}</td>
                                        <td className="px-6 py-4">
                                            <span
                                                className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                                    account.enabled
                                                        ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                                        : 'bg-surface-1 text-ink-muted'
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
                                                    className={`${actionClass} ${account.enabled ? 'text-red-600 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-500/15' : ''}`}
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
