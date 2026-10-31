import { weekdayName } from '@/lib/format';
import { CustomerChange, CustomerChangeEntity, CustomerChangeValue } from '../types';

const ENTITY_LABELS: Record<CustomerChangeEntity, string> = {
    customer: 'Datos del cliente',
    customer_address: 'Dirección',
    customer_preference: 'Preferencia de entrega',
};

const FIELD_LABELS: Record<string, string> = {
    name: 'Nombre',
    phone_number: 'Teléfono',
    enabled: 'Activo',
    street: 'Calle',
    apartment: 'Depto.',
    floor: 'Piso',
    visual_reference: 'Referencia',
    neighborhood: 'Barrio',
    latitude: 'Latitud',
    longitude: 'Longitud',
    is_primary: 'Principal',
    day_of_week: 'Día',
    time_window_start: 'Desde',
    time_window_end: 'Hasta',
};

function fieldLabel(field: string): string {
    const name = field.replace(/^address\./, '');
    const label = FIELD_LABELS[name] ?? name;

    return field.startsWith('address.') ? `Dirección · ${label}` : label;
}

function shown(field: string, value: CustomerChangeValue): string {
    if (value === null || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    if (field === 'day_of_week') return weekdayName(Number(value));

    return String(value);
}

/** What a driver proposed for another driver's customer, next to what the customer has now. */
export default function CustomerChangeFields({ change }: { change: CustomerChange }) {
    return (
        <div className="mt-2">
            <p className="text-xs font-medium text-ink-muted">
                {ENTITY_LABELS[change.entity]}
                {change.adds ? ' (nueva)' : ''}
            </p>
            <table className="mt-1 text-xs">
                <thead className="sr-only">
                    <tr>
                        <th>Campo</th>
                        <th>Actual</th>
                        <th>Propuesto</th>
                    </tr>
                </thead>
                <tbody>
                    {change.fields.map(({ field, current, proposed }) => (
                        <tr key={field}>
                            <td className="pr-3 py-0.5 text-ink-muted whitespace-nowrap">{fieldLabel(field)}</td>
                            <td
                                className={`pr-2 py-0.5 text-ink-subtle ${current !== proposed ? 'line-through decoration-ink-subtle/60' : ''}`}
                            >
                                {change.adds ? '' : shown(field, current)}
                            </td>
                            <td className="py-0.5 font-medium text-ink">{shown(field, proposed)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
