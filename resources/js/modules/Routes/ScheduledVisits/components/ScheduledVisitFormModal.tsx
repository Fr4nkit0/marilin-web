import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Button from '@/ui/Button';
import { Field, FieldError, Input, Textarea } from '@/ui/Field';
import Modal, { ModalTitle } from '@/ui/Modal';
import type { ScheduledVisitInput } from '../types';

interface Props {
    show: boolean;
    customerId: number;
    customerName: string;
    /** The earliest day that can be picked, aaaa-mm-dd. */
    today: string;
    onClose: () => void;
}

/** Asks the customer's driver to come by on a date: the visit becomes a stop on that day's route. */
export default function ScheduledVisitFormModal({ show, customerId, customerName, today, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<ScheduledVisitInput>({
        date: today,
        scheduled_time: '',
        note: '',
    });

    useEffect(() => {
        if (show) {
            reset();
            setData('date', today);
            clearErrors();
        }
    }, [show]);

    // A rule about the customer (no driver, disabled, no address) is not about any one field.
    const customerError = (errors as Record<string, string | undefined>).customer;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('scheduled-visits.store', customerId), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <ModalTitle className="text-lg font-semibold text-ink">Agendar visita</ModalTitle>
                        <p className="text-sm text-ink-muted mt-1">
                            {customerName}: el repartidor pasa ese día; en la visita se arregla la venta o el cobro.
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
                    <FieldError message={customerError} className="text-sm" />
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Fecha" error={errors.date}>
                            <Input
                                id="scheduled-visit-date"
                                type="date"
                                min={today}
                                value={data.date}
                                invalid={Boolean(errors.date)}
                                onChange={(e) => setData('date', e.target.value)}
                            />
                        </Field>
                        <Field label="Hora (opcional)" error={errors.scheduled_time}>
                            <Input
                                id="scheduled-visit-time"
                                type="time"
                                value={data.scheduled_time}
                                invalid={Boolean(errors.scheduled_time)}
                                onChange={(e) => setData('scheduled_time', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label="Nota (opcional)" error={errors.note} hint="El repartidor la ve en la parada.">
                        <Textarea
                            id="scheduled-visit-note"
                            value={data.note}
                            maxLength={500}
                            placeholder="Llamó, quiere 2 bidones"
                            invalid={Boolean(errors.note)}
                            onChange={(e) => setData('note', e.target.value)}
                        />
                    </Field>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" processing={processing}>
                        Agendar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
