import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import { PageHeader } from '@/ui/PageHeader';
import Button from '@/ui/Button';
import DataTable, { Column } from '@/ui/DataTable';
import SettingsTabs from './components/SettingsTabs';
import EnabledBadge from './components/EnabledBadge';
import RowActions from './components/RowActions';
import SettingStatusModal from './components/SettingStatusModal';
import ContainerTypeFormModal from './components/ContainerTypeFormModal';
import { ContainerTypeItem } from './types';

interface Props {
    containerTypes: ContainerTypeItem[];
}

export default function ContainerTypes({ containerTypes }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ContainerTypeItem | null>(null);
    const [statusFor, setStatusFor] = useState<ContainerTypeItem | null>(null);

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (containerType: ContainerTypeItem) => {
        setEditing(containerType);
        setFormOpen(true);
    };

    const columns: Column<ContainerTypeItem>[] = [
        {
            header: 'Tipo de envase',
            cell: (row) => <span className={`font-medium ${row.enabled ? 'text-ink' : 'text-ink-subtle'}`}>{row.description}</span>,
        },
        {
            header: 'Código',
            cell: (row) => <span className="font-mono text-xs text-ink-muted">{row.code}</span>,
        },
        {
            header: 'Estado',
            cell: (row) => <EnabledBadge enabled={row.enabled} />,
        },
        {
            header: 'Acciones',
            align: 'right',
            cell: (row) => (
                <RowActions label={row.description} enabled={row.enabled} onEdit={() => openEdit(row)} onToggle={() => setStatusFor(row)} />
            ),
        },
    ];

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Configuración</span>}>
            <Head title="Tipos de envase" />

            <PageHeader
                title="Configuración"
                description="Catálogos que la app recibe en cada sincronización."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="w-4 h-4" />
                        Nuevo tipo de envase
                    </Button>
                }
            />

            <SettingsTabs />

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                <DataTable columns={columns} rows={containerTypes} rowKey={(row) => row.id} empty="No hay tipos de envase cargados." />
            </div>

            <ContainerTypeFormModal show={formOpen} containerType={editing} onClose={() => setFormOpen(false)} />
            <SettingStatusModal
                show={statusFor !== null}
                enabled={statusFor?.enabled ?? true}
                title={statusFor?.enabled ? '¿Deshabilitar el tipo de envase?' : '¿Habilitar el tipo de envase?'}
                description={
                    statusFor?.enabled
                        ? `La app deja de ofrecer "${statusFor?.description}". Los saldos de envases de los clientes no cambian.`
                        : `La app vuelve a ofrecer "${statusFor?.description}" en la próxima sincronización.`
                }
                url={statusFor ? route('container-types.status', statusFor.id) : ''}
                onClose={() => setStatusFor(null)}
            />
        </AdminLayout>
    );
}
