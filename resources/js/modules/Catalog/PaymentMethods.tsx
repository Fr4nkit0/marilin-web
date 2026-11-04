import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import { PageHeader } from '@/ui/PageHeader';
import DataTable, { Column } from '@/ui/DataTable';
import SettingsTabs from './components/SettingsTabs';
import EnabledBadge from './components/EnabledBadge';
import RowActions from './components/RowActions';
import SettingStatusModal from './components/SettingStatusModal';
import PaymentMethodFormModal from './components/PaymentMethodFormModal';
import { PaymentMethodItem } from './types';

interface Props {
    paymentMethods: PaymentMethodItem[];
}

export default function PaymentMethods({ paymentMethods }: Props) {
    const [editing, setEditing] = useState<PaymentMethodItem | null>(null);
    const [statusFor, setStatusFor] = useState<PaymentMethodItem | null>(null);

    const columns: Column<PaymentMethodItem>[] = [
        {
            header: 'Medio de pago',
            cell: (row) => <span className={`font-medium ${row.enabled ? 'text-ink' : 'text-ink-subtle'}`}>{row.name}</span>,
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
            cell: (row) => <RowActions label={row.name} enabled={row.enabled} onEdit={() => setEditing(row)} onToggle={() => setStatusFor(row)} />,
        },
    ];

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Configuración</span>}>
            <Head title="Medios de pago" />

            <PageHeader title="Configuración" description="Catálogos que la app recibe en cada sincronización." />

            <SettingsTabs />

            <p className="mb-4 flex items-start gap-2 rounded-lg border border-hairline/60 bg-canvas-soft px-4 py-3 text-sm text-ink-muted">
                <Info className="w-4 h-4 mt-0.5 shrink-0 text-sky-600 dark:text-sky-300" />
                No se pueden agregar medios de pago nuevos: el cierre de caja cuenta los cobros por efectivo y transferencia. Podés
                cambiar el nombre que se muestra o deshabilitar uno.
            </p>

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                <DataTable columns={columns} rows={paymentMethods} rowKey={(row) => row.id} empty="No hay medios de pago cargados." />
            </div>

            <PaymentMethodFormModal paymentMethod={editing} onClose={() => setEditing(null)} />
            <SettingStatusModal
                show={statusFor !== null}
                enabled={statusFor?.enabled ?? true}
                title={statusFor?.enabled ? '¿Deshabilitar el medio de pago?' : '¿Habilitar el medio de pago?'}
                description={
                    statusFor?.enabled
                        ? `La app deja de ofrecer "${statusFor?.name}" para cobrar. Los cobros ya registrados no cambian.`
                        : `La app vuelve a ofrecer "${statusFor?.name}" en la próxima sincronización.`
                }
                url={statusFor ? route('payment-methods.status', statusFor.id) : ''}
                onClose={() => setStatusFor(null)}
            />
        </AdminLayout>
    );
}
