import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { ContainerTypeItem } from '../types';

interface Props {
    show: boolean;
    /** The container type being edited; null creates a new one. */
    containerType: ContainerTypeItem | null;
    onClose: () => void;
}

export default function ContainerTypeFormModal({ show, containerType, onClose }: Props) {
    const isNew = containerType === null;
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({ description: '' });

    useEffect(() => {
        if (!show) return;
        clearErrors();
        setData({ description: containerType?.description ?? '' });
    }, [show, containerType]);

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
            post(route('container-types.store'), options);
        } else {
            patch(route('container-types.update', containerType.id), options);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="px-6 pt-6 pb-4 border-b border-hairline">
                    <ModalTitle className="text-lg font-semibold text-ink">{isNew ? 'Nuevo tipo de envase' : 'Editar tipo de envase'}</ModalTitle>
                    <p className="text-sm text-ink-muted mt-1">
                        {isNew
                            ? 'El código se genera a partir de la descripción y no se puede cambiar.'
                            : `El código ${containerType.code} no cambia.`}
                    </p>
                </div>

                <div className="px-6 py-5">
                    <Field label="Descripción" error={errors.description}>
                        <Input
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            invalid={Boolean(errors.description)}
                            maxLength={160}
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
                        {isNew ? 'Crear tipo de envase' : 'Guardar cambios'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
