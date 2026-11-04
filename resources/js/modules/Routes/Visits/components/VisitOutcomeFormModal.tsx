import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Checkbox, Field, FieldError, Input } from '@/ui/Field';
import { VisitOutcomeItem } from '../types';

interface Props {
    show: boolean;
    /** The outcome being edited; null creates a new one. */
    outcome: VisitOutcomeItem | null;
    onClose: () => void;
}

export default function VisitOutcomeFormModal({ show, outcome, onClose }: Props) {
    const isNew = outcome === null;
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ name: '', triggers_reschedule: false });

    useEffect(() => {
        if (!show) return;
        clearErrors();
        setData({ name: outcome?.name ?? '', triggers_reschedule: outcome?.triggers_reschedule ?? false });
    }, [show, outcome]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };
        if (isNew) {
            post(route('visit-outcomes.store'), options);
        } else {
            patch(route('visit-outcomes.update', outcome.id), options);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="px-6 pt-6 pb-4 border-b border-hairline">
                    <ModalTitle className="text-lg font-semibold text-ink">{isNew ? 'Nuevo resultado de visita' : 'Editar resultado de visita'}</ModalTitle>
                    <p className="text-sm text-ink-muted mt-1">El repartidor lo elige en la app a partir de la próxima sincronización.</p>
                </div>

                <div className="px-6 py-5 space-y-4">
                    <Field label="Nombre" error={errors.name}>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            invalid={Boolean(errors.name)}
                            maxLength={100}
                            autoComplete="off"
                            autoFocus
                        />
                    </Field>

                    <div className="space-y-1.5">
                        <label className="flex items-start gap-2 text-sm text-ink">
                            <Checkbox
                                checked={data.triggers_reschedule}
                                onChange={(e) => setData('triggers_reschedule', e.target.checked)}
                                className="mt-0.5"
                            />
                            <span>
                                Reprograma la visita
                                <span className="block text-xs text-ink-muted">
                                    El repartidor elige una hora más tarde en el día para volver a pasar.
                                </span>
                            </span>
                        </label>
                        <FieldError message={errors.triggers_reschedule} />
                    </div>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" processing={processing}>
                        {isNew ? 'Crear resultado' : 'Guardar cambios'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
