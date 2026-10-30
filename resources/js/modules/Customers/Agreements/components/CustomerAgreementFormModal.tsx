import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { FieldError } from '@/ui/Field';
import { X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import { AgreementProduct } from '../types';
import { money } from '@/lib/format';

interface Props {
    show: boolean;
    customerId: number;
    customerName: string;
    products: AgreementProduct[];
    today: string;
    onClose: () => void;
}

type AgreementForm = {
    product_id: string;
    unit_price: string;
    valid_from: string;
    valid_to: string;
    reason: string;
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600';

export default function CustomerAgreementFormModal({ show, customerId, customerName, products, today, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<AgreementForm>({
        product_id: '',
        unit_price: '',
        valid_from: today,
        valid_to: '',
        reason: '',
    });

    useEffect(() => {
        if (!show) {
            reset();
            clearErrors();
        }
    }, [show]);

    // Empty optional fields travel as null so the server stores "no end" and "no reason".
    transform((form) => ({
        ...form,
        valid_to: form.valid_to || null,
        reason: form.reason.trim() || null,
    }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('customers.agreements.store', customerId), { preserveScroll: true, onSuccess: onClose });
    };

    const listPrice = products.find((product) => product.id === Number(data.product_id))?.price;

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <ModalTitle className="text-lg font-semibold text-ink">Nuevo acuerdo de precio</ModalTitle>
                        <p className="text-sm text-ink-muted mt-1">
                            {customerName}: el precio acordado reemplaza al de lista y llega a la app en la próxima
                            sincronización.
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
                    <label className="block">
                        <span className="text-sm font-medium text-ink-secondary">Producto</span>
                        <select
                            value={data.product_id}
                            onChange={(e) => setData('product_id', e.target.value)}
                            className={`${inputClass} mt-1`}
                            required
                        >
                            <option value="">Elegí un producto</option>
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.name}
                                </option>
                            ))}
                        </select>
                        <FieldError message={errors.product_id} className="mt-1" />
                    </label>

                    <label className="block">
                        <span className="text-sm font-medium text-ink-secondary">Precio acordado</span>
                        <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputMode="decimal"
                            value={data.unit_price}
                            onChange={(e) => setData('unit_price', e.target.value)}
                            className={`${inputClass} mt-1`}
                            required
                        />
                        {listPrice !== undefined && (
                            <p className="text-xs text-ink-muted mt-1">
                                Precio de lista: {money(listPrice)}
                            </p>
                        )}
                        <FieldError message={errors.unit_price} className="mt-1" />
                    </label>

                    <div className="grid grid-cols-2 gap-4">
                        <label className="block">
                            <span className="text-sm font-medium text-ink-secondary">Desde</span>
                            <input
                                type="date"
                                value={data.valid_from}
                                onChange={(e) => setData('valid_from', e.target.value)}
                                style={{ colorScheme: 'light' }}
                                className={`${inputClass} mt-1`}
                                required
                            />
                            <FieldError message={errors.valid_from} className="mt-1" />
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-ink-secondary">Hasta (opcional)</span>
                            <input
                                type="date"
                                value={data.valid_to}
                                min={data.valid_from}
                                onChange={(e) => setData('valid_to', e.target.value)}
                                style={{ colorScheme: 'light' }}
                                className={`${inputClass} mt-1`}
                            />
                            <FieldError message={errors.valid_to} className="mt-1" />
                        </label>
                    </div>

                    <label className="block">
                        <span className="text-sm font-medium text-ink-secondary">Motivo (opcional)</span>
                        <textarea
                            value={data.reason}
                            maxLength={500}
                            rows={2}
                            onChange={(e) => setData('reason', e.target.value)}
                            className={`${inputClass} mt-1`}
                        />
                        <FieldError message={errors.reason} className="mt-1" />
                    </label>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-ink-secondary bg-canvas ring-1 ring-inset ring-hairline/80 hover:bg-canvas-soft cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                    >
                        Crear acuerdo
                    </button>
                </div>
            </form>
        </Modal>
    );
}
