import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import { PageHeader } from '@/ui/PageHeader';
import Button from '@/ui/Button';
import DataTable, { Column } from '@/ui/DataTable';
import SettingsTabs from '@/modules/Catalog/components/SettingsTabs';
import EnabledBadge from '@/modules/Catalog/components/EnabledBadge';
import RowActions from '@/modules/Catalog/components/RowActions';
import SettingStatusModal from '@/modules/Catalog/components/SettingStatusModal';
import VisitOutcomeFormModal from './components/VisitOutcomeFormModal';
import { VisitOutcomeItem } from './types';

interface Props {
    outcomes: VisitOutcomeItem[];
}

export default function Outcomes({ outcomes }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<VisitOutcomeItem | null>(null);
    const [statusFor, setStatusFor] = useState<VisitOutcomeItem | null>(null);

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (outcome: VisitOutcomeItem) => {
        setEditing(outcome);
        setFormOpen(true);
    };

    const columns: Column<VisitOutcomeItem>[] = [
        {
            header: 'Resultado',
            cell: (row) => <span className={`font-medium ${row.enabled ? 'text-ink' : 'text-ink-subtle'}`}>{row.name}</span>,
        },
        {
            header: 'Código',
            cell: (row) => <span className="font-mono text-xs text-ink-muted">{row.code ?? '—'}</span>,
        },
        {
            header: 'Reprograma',
            cell: (row) => <span className="text-ink-muted">{row.triggers_reschedule ? 'Sí' : 'No'}</span>,
        },
        {
            header: 'Estado',
            cell: (row) => <EnabledBadge enabled={row.enabled} />,
        },
        {
            header: 'Acciones',
            align: 'right',
            cell: (row) => (
                <RowActions label={row.name} enabled={row.enabled} onEdit={() => openEdit(row)} onToggle={() => setStatusFor(row)} />
            ),
        },
    ];

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Configuración</span>}>
            <Head title="Resultados de visita" />

            <PageHeader
                title="Configuración"
                description="Catálogos que la app recibe en cada sincronización."
                actions={
                    <Button onClick={openCreate}>
                        <Plus className="w-4 h-4" />
                        Nuevo resultado
                    </Button>
                }
            />

            <SettingsTabs />

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                <DataTable columns={columns} rows={outcomes} rowKey={(row) => row.id} empty="No hay resultados de visita cargados." />
            </div>

            <VisitOutcomeFormModal show={formOpen} outcome={editing} onClose={() => setFormOpen(false)} />
            <SettingStatusModal
                show={statusFor !== null}
                enabled={statusFor?.enabled ?? true}
                title={statusFor?.enabled ? '¿Deshabilitar el resultado?' : '¿Habilitar el resultado?'}
                description={
                    statusFor?.enabled
                        ? `La app deja de ofrecer "${statusFor?.name}" al terminar una visita. Las visitas que ya lo tienen no cambian.`
                        : `La app vuelve a ofrecer "${statusFor?.name}" en la próxima sincronización.`
                }
                url={statusFor ? route('visit-outcomes.status', statusFor.id) : ''}
                onClose={() => setStatusFor(null)}
            />
        </AdminLayout>
    );
}
