import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal from '@/Components/Modal';
import { AgreementProduct } from '@/types/agreement';

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
    'w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600';

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
            <form onSubmit={submit}>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Nuevo acuerdo de precio</h2>
                        <p className="text-sm text-slate-500 mt-1">
                            {customerName}: el precio acordado reemplaza al de lista y llega a la app en la próxima
                            sincronización.
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
                        <span className="text-sm font-medium text-slate-700">Producto</span>
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
                        {errors.product_id && <p className="text-sm text-red-600 mt-1">{errors.product_id}</p>}
                    </label>

                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Precio acordado</span>
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
                            <p className="text-xs text-slate-500 mt-1">
                                Precio de lista: ${listPrice.toLocaleString('es-AR')}
                            </p>
                        )}
                        {errors.unit_price && <p className="text-sm text-red-600 mt-1">{errors.unit_price}</p>}
                    </label>

                    <div className="grid grid-cols-2 gap-4">
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Desde</span>
                            <input
                                type="date"
                                value={data.valid_from}
                                onChange={(e) => setData('valid_from', e.target.value)}
                                style={{ colorScheme: 'light' }}
                                className={`${inputClass} mt-1`}
                                required
                            />
                            {errors.valid_from && <p className="text-sm text-red-600 mt-1">{errors.valid_from}</p>}
                        </label>
                        <label className="block">
                            <span className="text-sm font-medium text-slate-700">Hasta (opcional)</span>
                            <input
                                type="date"
                                value={data.valid_to}
                                min={data.valid_from}
                                onChange={(e) => setData('valid_to', e.target.value)}
                                style={{ colorScheme: 'light' }}
                                className={`${inputClass} mt-1`}
                            />
                            {errors.valid_to && <p className="text-sm text-red-600 mt-1">{errors.valid_to}</p>}
                        </label>
                    </div>

                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Motivo (opcional)</span>
                        <textarea
                            value={data.reason}
                            maxLength={500}
                            rows={2}
                            onChange={(e) => setData('reason', e.target.value)}
                            className={`${inputClass} mt-1`}
                        />
                        {errors.reason && <p className="text-sm text-red-600 mt-1">{errors.reason}</p>}
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
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-500 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        Crear acuerdo
                    </button>
                </div>
            </form>
        </Modal>
    );
}
