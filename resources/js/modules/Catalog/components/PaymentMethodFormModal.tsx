import { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { PaymentMethodItem } from '../types';

interface Props {
    /** The payment method being renamed; null keeps the modal closed. */
    paymentMethod: PaymentMethodItem | null;
    onClose: () => void;
}

export default function PaymentMethodFormModal({ paymentMethod, onClose }: Props) {
    const { data, setData, patch, processing, errors, reset, clearErrors } = useForm({ name: '' });

    useEffect(() => {
        if (paymentMethod === null) return;
        clearErrors();
        setData({ name: paymentMethod.name });
    }, [paymentMethod]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (paymentMethod === null) return;
        patch(route('payment-methods.update', paymentMethod.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal show={paymentMethod !== null} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="px-6 pt-6 pb-4 border-b border-hairline">
                    <ModalTitle className="text-lg font-semibold text-ink">Editar medio de pago</ModalTitle>
                    <p className="text-sm text-ink-muted mt-1">El código {paymentMethod?.code} no cambia.</p>
                </div>

                <div className="px-6 py-5">
                    <Field label="Nombre" error={errors.name}>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            invalid={Boolean(errors.name)}
                            maxLength={80}
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
                        Guardar cambios
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
