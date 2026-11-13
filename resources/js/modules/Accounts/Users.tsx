import React, { FormEvent, useState } from 'react';
import { Head } from '@inertiajs/react';
import { useListFilters } from '@/lib/useListFilters';
import { KeyRound, Pencil, Plus, Power, Search, UserRound } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import Button from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';
import DriverAccountFormModal from './components/DriverAccountFormModal';
import DriverPasswordModal from './components/DriverPasswordModal';
import DriverStatusModal from './components/DriverStatusModal';
import { DriverAccount, DriverAccountStatusFilter } from '@/modules/Accounts/types';

interface Props {
    users: DriverAccount[];
    filters: { search: string; status: DriverAccountStatusFilter };
}

/** A quiet row action whose hover tint says what it does; the confirmation modal guards the change. */
const rowAction =
    'inline-flex h-8 items-center justify-center gap-2 rounded-md px-3 text-xs font-medium text-ink-secondary transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2';

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

    const filtered = Boolean(filters.search) || filters.status !== null;

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

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Usuarios</span>}>
            <Head title="Usuarios" />

            <PageHeader
                title="Usuarios"
                description="Cuentas de repartidor para entrar a la app. Cada cuenta que creás acá es un perfil de repartidor."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="w-4 h-4" />
                        Nuevo repartidor
                    </Button>
                }
            />

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <form onSubmit={submitSearch} className="relative flex-1">
                    <Search className="w-4 h-4 text-ink-subtle absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="search"
                        value={filter.values.search}
                        onChange={(e) => filter.draft('search', e.target.value)}
                        placeholder="Buscar por nombre o usuario"
                        aria-label="Buscar usuarios"
                        className="w-full h-10 pl-9 pr-3 text-sm border border-hairline bg-canvas text-ink placeholder-ink-subtle rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600"
                    />
                </form>
                <div className="grid grid-cols-3 gap-1 rounded-lg bg-surface-1 p-1 sm:flex" role="group" aria-label="Filtrar por estado">
                    {statusOptions.map((option) => (
                        <button
                            key={option.label}
                            type="button"
                            onClick={() => filter.set('status', option.value ?? '')}
                            aria-pressed={filters.status === option.value}
                            className={`min-h-10 px-3 py-2 sm:min-h-0 sm:py-1.5 rounded-md text-xs font-medium transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 ${
                                filters.status === option.value ? 'bg-canvas text-ink shadow-sm' : 'text-ink-muted hover:text-ink'
                            }`}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                {users.length === 0 ? (
                    <div className="px-6 py-14 text-center">
                        <UserRound className="w-10 h-10 text-ink-subtle mx-auto mb-3" />
                        {filtered ? (
                            <>
                                <h2 className="text-base font-semibold text-ink">No hay cuentas que coincidan</h2>
                                <p className="text-sm text-ink-muted mt-1">Probá con otro nombre o cambiá el filtro de estado.</p>
                                <Button
                                    variant="secondary"
                                    className="mt-5"
                                    onClick={() => filter.setMany({ search: '', status: '' })}
                                >
                                    Limpiar filtros
                                </Button>
                            </>
                        ) : (
                            <>
                                <h2 className="text-base font-semibold text-ink">Todavía no hay repartidores</h2>
                                <p className="text-sm text-ink-muted mt-1">
                                    Creá la cuenta de un repartidor para que pueda entrar a la app.
                                </p>
                                <Button className="mt-5" onClick={openCreate}>
                                    <Plus className="w-4 h-4" />
                                    Nuevo repartidor
                                </Button>
                            </>
                        )}
                    </div>
                ) : (
                    <div className="table-scroll">
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="bg-canvas-soft border-b border-hairline text-ink-muted">
                                <tr>
                                    <th className="px-5 py-3 text-xs font-medium">Repartidor</th>
                                    <th className="px-5 py-3 text-xs font-medium">Usuario de la app</th>
                                    <th className="px-5 py-3 text-xs font-medium">Teléfono</th>
                                    <th className="px-5 py-3 text-xs font-medium">Estado</th>
                                    <th className="px-5 py-3 text-xs font-medium text-right">
                                        <span className="sr-only">Acciones</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-hairline">
                                {users.map((account) => (
                                    <tr key={account.id} className="hover:bg-canvas-soft/50 transition-colors">
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <span
                                                    aria-hidden="true"
                                                    className={`flex w-9 h-9 shrink-0 items-center justify-center rounded-full bg-surface-1 font-semibold ${
                                                        account.enabled ? 'text-ink-secondary' : 'text-ink-subtle'
                                                    }`}
                                                >
                                                    {account.name.charAt(0)}
                                                </span>
                                                <span className={`font-medium ${account.enabled ? 'text-ink' : 'text-ink-muted'}`}>
                                                    {account.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-ink-secondary">
                                            {account.username ?? <span className="text-ink-subtle">—</span>}
                                        </td>
                                        <td className="px-5 py-3 text-ink-secondary tabular-nums whitespace-nowrap">
                                            {account.phone_number ?? <span className="text-ink-subtle">—</span>}
                                        </td>
                                        <td className="px-5 py-3">
                                            <span
                                                className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full border text-xs font-medium ${
                                                    account.enabled
                                                        ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30'
                                                        : 'bg-surface-1 text-ink-muted border-hairline/60'
                                                }`}
                                            >
                                                <span className="w-1.5 h-1.5 rounded-full bg-current" />
                                                {account.enabled ? 'Habilitada' : 'Deshabilitada'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(account)}
                                                    aria-label={`Editar a ${account.name}`}
                                                >
                                                    <Pencil className="w-3.5 h-3.5" />
                                                    Editar
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => setPasswordFor(account)}
                                                    aria-label={`Cambiar la contraseña de ${account.name}`}
                                                >
                                                    <KeyRound className="w-3.5 h-3.5" />
                                                    Contraseña
                                                </Button>
                                                <button
                                                    type="button"
                                                    onClick={() => setStatusFor(account)}
                                                    aria-label={`${account.enabled ? 'Deshabilitar' : 'Habilitar'} la cuenta de ${account.name}`}
                                                    className={`${rowAction} ${
                                                        account.enabled
                                                            ? 'hover:text-rose-700 hover:bg-rose-50 dark:hover:text-rose-300 dark:hover:bg-rose-500/15'
                                                            : 'hover:text-ink hover:bg-surface-1'
                                                    }`}
                                                >
                                                    <Power className="w-3.5 h-3.5" />
                                                    {account.enabled ? 'Deshabilitar' : 'Habilitar'}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <DriverAccountFormModal show={formOpen} account={editing} onClose={() => setFormOpen(false)} />
            <DriverPasswordModal account={passwordFor} onClose={() => setPasswordFor(null)} />
            <DriverStatusModal account={statusFor} onClose={() => setStatusFor(null)} />
        </AdminLayout>
    );
}
