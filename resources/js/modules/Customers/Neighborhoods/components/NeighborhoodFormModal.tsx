import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { Neighborhood } from '../types';

interface Props {
    show: boolean;
    /** The neighborhood being renamed; null creates a new one. */
    neighborhood: Neighborhood | null;
    onClose: () => void;
}

export default function NeighborhoodFormModal({ show, neighborhood, onClose }: Props) {
    const isNew = neighborhood === null;
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ name: '' });

    useEffect(() => {
        if (!show) return;
        clearErrors();
        setData({ name: neighborhood?.name ?? '' });
    }, [show, neighborhood]);

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
            post(route('neighborhoods.store'), options);
        } else {
            patch(route('neighborhoods.update', neighborhood.id), options);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="px-6 pt-6 pb-4 border-b border-hairline">
                    <ModalTitle className="text-lg font-semibold text-ink">{isNew ? 'Nuevo barrio' : 'Editar barrio'}</ModalTitle>
                    <p className="text-sm text-ink-muted mt-1">
                        {isNew
                            ? 'El barrio llega a la app en la próxima sincronización.'
                            : `Las direcciones cargadas con "${neighborhood.name}" pasan al nombre nuevo.`}
                    </p>
                </div>

                <div className="px-6 py-5">
                    <Field label="Nombre" error={errors.name}>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            invalid={Boolean(errors.name)}
                            maxLength={255}
                            autoComplete="off"
                            autoFocus
                        />
                    </Field>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" processing={processing}>
                        {isNew ? 'Crear barrio' : 'Guardar cambios'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
