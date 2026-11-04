import { FormEvent, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import { PageHeader } from '@/ui/PageHeader';
import Button from '@/ui/Button';
import DataTable, { Column } from '@/ui/DataTable';
import Pagination from '@/ui/Pagination';
import { useListFilters } from '@/lib/useListFilters';
import { PaginatedData } from '@/types';
import SettingsTabs from '@/modules/Catalog/components/SettingsTabs';
import NeighborhoodFormModal from './components/NeighborhoodFormModal';
import NeighborhoodStatusModal from './components/NeighborhoodStatusModal';
import { Neighborhood, NeighborhoodFilters } from './types';

interface Props {
    neighborhoods: PaginatedData<Neighborhood>;
    filters: NeighborhoodFilters;
}

export default function Index({ neighborhoods, filters }: Props) {
    const filter = useListFilters('neighborhoods.index', { search: filters.search ?? '' }, { search: '' }, { preserveScroll: true });
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Neighborhood | null>(null);
    const [statusFor, setStatusFor] = useState<Neighborhood | null>(null);

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        filter.submit();
    };

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (neighborhood: Neighborhood) => {
        setEditing(neighborhood);
        setFormOpen(true);
    };

    const columns: Column<Neighborhood>[] = [
        {
            header: 'Barrio',
            cell: (row) => <span className={`font-medium ${row.enabled ? 'text-ink' : 'text-ink-subtle'}`}>{row.name}</span>,
        },
        {
            header: 'Estado',
            cell: (row) => (
                <span
                    className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                        row.enabled
                            ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                            : 'bg-surface-1 text-ink-muted'
                    }`}
                >
                    {row.enabled ? 'Habilitado' : 'Deshabilitado'}
                </span>
            ),
        },
        {
            header: 'Acciones',
            align: 'right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="sm" onClick={() => openEdit(row)} aria-label={`Editar ${row.name}`}>
                        <Pencil className="w-3.5 h-3.5" />
                        Editar
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setStatusFor(row)}
                        aria-label={`${row.enabled ? 'Deshabilitar' : 'Habilitar'} ${row.name}`}
                        className={row.enabled ? 'text-rose-600 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-500/15' : ''}
                    >
                        <Power className="w-3.5 h-3.5" />
                        {row.enabled ? 'Deshabilitar' : 'Habilitar'}
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Configuración</span>}>
            <Head title="Barrios" />

            <PageHeader
                title="Configuración"
                description="Catálogos que la app recibe en cada sincronización."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="w-4 h-4" />
                        Nuevo barrio
                    </Button>
                }
            />

            <SettingsTabs />

            <form onSubmit={submitSearch} className="relative mb-4">
                <Search className="w-4 h-4 text-ink-subtle absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                    type="search"
                    value={filter.values.search}
                    onChange={(e) => filter.draft('search', e.target.value)}
                    placeholder="Buscar por nombre"
                    aria-label="Buscar barrios"
                    className="w-full py-2 pl-9 pr-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600"
                />
            </form>

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                <DataTable
                    columns={columns}
                    rows={neighborhoods.data}
                    rowKey={(row) => row.id}
                    empty="No hay barrios que coincidan."
                />
                <Pagination page={neighborhoods} noun="barrios" />
            </div>

            <NeighborhoodFormModal show={formOpen} neighborhood={editing} onClose={() => setFormOpen(false)} />
            <NeighborhoodStatusModal neighborhood={statusFor} onClose={() => setStatusFor(null)} />
        </AdminLayout>
    );
}
