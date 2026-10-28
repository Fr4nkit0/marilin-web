import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import { HomePaymentMethod } from '@/modules/Street/types';

interface Props {
    show: boolean;
    customerId: number;
    customerName: string;
    paymentMethods: HomePaymentMethod[];
    onClose: () => void;
}

type HomePaymentForm = {
    amount: string;
    payment_method_id: string;
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600';

export default function HomePaymentModal({ show, customerId, customerName, paymentMethods, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<HomePaymentForm>({
        amount: '',
        payment_method_id: '',
    });

    useEffect(() => {
        if (!show) {
            reset();
            clearErrors();
        }
    }, [show]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('customers.home-payments.store', customerId), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit}>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <h2 className="text-lg font-semibold text-ink">Cobro en casa</h2>
                        <p className="text-sm text-ink-muted mt-1">
                            {customerName}: baja la deuda del cliente y no pertenece a ningún repartidor.
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
                        <span className="text-sm font-medium text-ink-secondary">Monto</span>
                        <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            inputMode="decimal"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className={`${inputClass} mt-1`}
                            required
                        />
                        {errors.amount && <p className="text-sm text-red-600 dark:text-red-300 mt-1">{errors.amount}</p>}
                    </label>

                    <label className="block">
                        <span className="text-sm font-medium text-ink-secondary">Método de pago</span>
                        <select
                            value={data.payment_method_id}
                            onChange={(e) => setData('payment_method_id', e.target.value)}
                            className={`${inputClass} mt-1`}
                            required
                        >
                            <option value="">Elegí un método</option>
                            {paymentMethods.map((method) => (
                                <option key={method.id} value={method.id}>
                                    {method.name}
                                </option>
                            ))}
                        </select>
                        {errors.payment_method_id && <p className="text-sm text-red-600 dark:text-red-300 mt-1">{errors.payment_method_id}</p>}
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
                        Registrar cobro
                    </button>
                </div>
            </form>
        </Modal>
    );
}
