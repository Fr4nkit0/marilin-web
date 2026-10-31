import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Field, Textarea } from '@/ui/Field';

/** What is being cancelled, as the confirmation shows it. */
export interface CancelTarget {
    /** Where the cancellation is sent (sales.cancel or payments.cancel). */
    url: string;
    /** "Anular venta #12" */
    title: string;
    /** Who and how much, so the admin knows which record they are about to cancel. */
    summary: string;
}

interface Props {
    target: CancelTarget | null;
    /** What reversing it does, in one sentence. */
    consequence: string;
    onClose: () => void;
}

type CancelForm = { reason: string };

/**
 * The admin's confirmation before cancelling a sale or payment of any day: the reason is mandatory and stays on the record.
 */
export default function CancelRecordModal({ target, consequence, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<CancelForm>({ reason: '' });

    useEffect(() => {
        if (target === null) {
            reset();
            clearErrors();
        }
    }, [target]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (target === null) {
            return;
        }
        post(target.url, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal show={target !== null} onClose={onClose} maxWidth="md">
            {target && (
                <form onSubmit={submit} noValidate>
                    <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                        <div>
                            <ModalTitle className="text-lg font-semibold text-ink">{target.title}</ModalTitle>
                            <p className="text-sm text-ink-muted mt-1">{target.summary}</p>
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
                        <p className="rounded-lg border border-amber-200/60 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/15 px-3 py-2 text-sm text-amber-800 dark:text-amber-200">
                            {consequence} La anulación queda registrada con tu usuario y llega a la app del repartidor.
                        </p>

                        <Field label="Motivo" error={errors.reason} hint="Obligatorio. Queda guardado en el registro.">
                            <Textarea
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                maxLength={500}
                                rows={3}
                                invalid={Boolean(errors.reason)}
                                required
                                autoFocus
                            />
                        </Field>
                    </div>

                    <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                        <Button variant="secondary" onClick={onClose}>
                            Volver
                        </Button>
                        <Button type="submit" variant="danger" processing={processing}>
                            Anular
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}
