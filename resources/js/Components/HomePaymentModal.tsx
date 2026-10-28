import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import { HomePaymentMethod } from '@/types/home-operation';

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
    'w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20 focus:border-slate-900';

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
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Cobro en casa</h2>
                        <p className="text-sm text-slate-500 mt-1">
                            {customerName}: baja la deuda del cliente y no pertenece a ningún repartidor.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cerrar"
                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="px-6 py-5 space-y-4">
                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Monto</span>
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
                        {errors.amount && <p className="text-sm text-red-600 mt-1">{errors.amount}</p>}
                    </label>

                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Método de pago</span>
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
                        {errors.payment_method_id && <p className="text-sm text-red-600 mt-1">{errors.payment_method_id}</p>}
                    </label>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-slate-50/50">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-slate-700 bg-white ring-1 ring-inset ring-slate-200/80 hover:bg-slate-50 cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        Registrar cobro
                    </button>
                </div>
            </form>
        </Modal>
    );
}
