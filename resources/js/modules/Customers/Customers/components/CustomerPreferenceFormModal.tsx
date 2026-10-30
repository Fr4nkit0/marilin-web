import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Button from '@/ui/Button';
import { Field, Input, Select } from '@/ui/Field';
import Modal, { ModalTitle } from '@/ui/Modal';
import { weekdayName } from '@/lib/format';
import { CustomerPreferenceRow, PreferenceInput } from '../types';

interface Props {
    show: boolean;
    customerId: number;
    /** The preference being changed; null adds a new one. */
    preference: CustomerPreferenceRow | null;
    onClose: () => void;
}

const DAYS = [1, 2, 3, 4, 5, 6, 0];

const formFor = (preference: CustomerPreferenceRow | null): PreferenceInput => ({
    day_of_week: String(preference?.dayOfWeek ?? 1),
    time_window_start: preference?.timeWindowStart ?? '',
    time_window_end: preference?.timeWindowEnd ?? '',
});

/** Adds or changes one delivery preference from the customer page. */
export default function CustomerPreferenceFormModal({ show, customerId, preference, onClose }: Props) {
    const { data, setData, post, put, processing, errors, clearErrors } = useForm<PreferenceInput>(formFor(preference));

    useEffect(() => {
        if (show) {
            setData(formFor(preference));
            clearErrors();
        }
    }, [show, preference]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (preference) {
            put(route('customers.preferences.update', [customerId, preference.id]), options);
        } else {
            post(route('customers.preferences.store', customerId), options);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <ModalTitle className="text-lg font-semibold text-ink">
                            {preference ? 'Editar día y horario' : 'Agregar día y horario'}
                        </ModalTitle>
                        <p className="text-sm text-ink-muted mt-1">
                            Sin horario, el cliente recibe en cualquier momento del día.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cerrar"
                        className="p-1.5 rounded-lg text-ink-subtle hover:text-ink-secondary hover:bg-surface-1 transition cursor-pointer"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="px-6 py-5 space-y-4">
                    <Field label="Día" error={errors.day_of_week}>
                        <Select
                            id="preference-day"
                            value={data.day_of_week}
                            invalid={Boolean(errors.day_of_week)}
                            onChange={(e) => setData('day_of_week', e.target.value)}
                        >
                            {DAYS.map((day) => (
                                <option key={day} value={day}>
                                    {weekdayName(day)}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Desde (opcional)" error={errors.time_window_start}>
                            <Input
                                id="preference-start"
                                type="time"
                                value={data.time_window_start}
                                invalid={Boolean(errors.time_window_start)}
                                onChange={(e) => setData('time_window_start', e.target.value)}
                            />
                        </Field>
                        <Field label="Hasta (opcional)" error={errors.time_window_end}>
                            <Input
                                id="preference-end"
                                type="time"
                                value={data.time_window_end}
                                invalid={Boolean(errors.time_window_end)}
                                onChange={(e) => setData('time_window_end', e.target.value)}
                            />
                        </Field>
                    </div>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" processing={processing}>
                        {preference ? 'Guardar cambios' : 'Agregar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
