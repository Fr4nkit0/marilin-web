import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Field, FieldError, Input, Textarea } from '@/ui/Field';
import { ReviewItem } from '../types';

interface Props {
    /** The flagged sale whose truck load is being corrected; null keeps the modal closed. */
    item: ReviewItem | null;
    onClose: () => void;
}

type CorrectionForm = { quantities: Record<number, string>; reason: string };

/** What brings each product the sale sold back to zero on the truck, as the starting quantity. */
function shortfalls(item: ReviewItem | null): Record<number, string> {
    return Object.fromEntries(
        (item?.stock_correction?.products ?? []).map((product) => [product.id, product.current < 0 ? String(-product.current) : '']),
    );
}

/**
 * "Corregir carga": the admin adds the units the truck load was written down short, with a mandatory reason,
 * and the sale is marked reviewed. The corrected stock reaches the driver's app in the next sync.
 */
export default function CorrectLoadModal({ item, onClose }: Props) {
    const { data, setData, post, processing, errors, setDefaults, reset, clearErrors, transform } = useForm<CorrectionForm>({
        quantities: {},
        reason: '',
    });

    useEffect(() => {
        clearErrors();
        if (item === null) {
            reset();
        } else {
            setDefaults({ quantities: shortfalls(item), reason: '' });
            setData({ quantities: shortfalls(item), reason: '' });
        }
    }, [item]);

    // Only the products the admin filled in travel.
    transform((form) => ({
        items: Object.entries(form.quantities)
            .filter(([, quantity]) => quantity !== '')
            .map(([productId, quantity]) => ({ product_id: Number(productId), quantity })),
        reason: form.reason,
    }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (item === null) return;
        post(route('review.stock-correction', item.id), { preserveScroll: true, onSuccess: onClose });
    };

    const products = item?.stock_correction?.products ?? [];
    const itemErrors = Object.entries(errors as Record<string, string>).filter(([field]) => field.startsWith('items'));

    return (
        <Modal show={item !== null} onClose={onClose} maxWidth="md">
            {item && (
                <form onSubmit={submit} noValidate>
                    <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                        <div>
                            <ModalTitle className="text-lg font-semibold text-ink">Corregir carga</ModalTitle>
                            <p className="text-sm text-ink-muted mt-1">
                                Venta #{item.id} de {item.customer}
                                {item.driver ? `, ruta de ${item.driver}` : ''}: suma a la carga del camión las unidades
                                que faltó anotar. Llega a la app en la próxima sincronización.
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
                        <div className="space-y-3">
                            {products.map((product) => (
                                <div key={product.id} className="flex items-center justify-between gap-4">
                                    <label htmlFor={`correction-${product.id}`} className="text-sm font-medium text-ink">
                                        {product.name}
                                        <span
                                            className={`block text-xs font-normal tabular-nums ${product.current < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-ink-muted'}`}
                                        >
                                            En el camión: {product.current}
                                        </span>
                                    </label>
                                    <Input
                                        id={`correction-${product.id}`}
                                        type="number"
                                        min="1"
                                        step="1"
                                        inputMode="numeric"
                                        placeholder="0"
                                        value={data.quantities[product.id] ?? ''}
                                        onChange={(e) => setData('quantities', { ...data.quantities, [product.id]: e.target.value })}
                                        className="w-28 text-right tabular-nums"
                                    />
                                </div>
                            ))}
                            {itemErrors.map(([field, message]) => (
                                <FieldError key={field} message={message} />
                            ))}
                        </div>

                        <Field label="Motivo" error={errors.reason} hint="Obligatorio. Queda guardado en el historial de carga.">
                            <Textarea
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                                maxLength={500}
                                rows={3}
                                invalid={Boolean(errors.reason)}
                                required
                            />
                        </Field>
                    </div>

                    <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                        <Button variant="secondary" onClick={onClose}>
                            Volver
                        </Button>
                        <Button type="submit" processing={processing}>
                            Corregir y marcar revisado
                        </Button>
                    </div>
                </form>
            )}
        </Modal>
    );
}
