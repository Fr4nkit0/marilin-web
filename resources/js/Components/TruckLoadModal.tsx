import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Package, X } from 'lucide-react';
import Modal from '@/ui/Modal';

export interface LoadableProduct {
    id: number;
    name: string;
}

interface Props {
    routeId: number | null;
    driverName: string;
    isFirstLoad: boolean;
    products: LoadableProduct[];
    onClose: () => void;
}

type LoadForm = { quantities: Record<number, string> };

export default function TruckLoadModal({ routeId, driverName, isFirstLoad, products, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<LoadForm>({
        quantities: {},
    });

    useEffect(() => {
        if (routeId === null) {
            reset();
            clearErrors();
        }
    }, [routeId]);

    // Only the products the admin filled in travel; the rest stay out of the load.
    transform((form) => ({
        items: products
            .map((product) => ({ product_id: product.id, quantity: form.quantities[product.id] ?? '' }))
            .filter((item) => item.quantity !== ''),
    }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (routeId === null) return;
        post(route('routes.loads.store', routeId), { preserveScroll: true, onSuccess: onClose });
    };

    const errorList = Object.entries(errors as Record<string, string>);
    const nothingFilled = Object.values(data.quantities).every((quantity) => quantity === '');

    return (
        <Modal show={routeId !== null} onClose={onClose} maxWidth="md">
            <form onSubmit={submit}>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            {isFirstLoad ? 'Carga inicial del camión' : 'Recarga del camión'}
                        </h2>
                        <p className="text-sm text-slate-500 mt-1">
                            {driverName}: lo que se carga se suma al stock y llega a la app en la próxima
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

                <div className="px-6 py-5 space-y-3">
                    {products.map((product) => (
                        <label key={product.id} className="flex items-center justify-between gap-4">
                            <span className="flex items-center gap-2 text-sm font-medium text-slate-900">
                                <Package className="w-4 h-4 text-slate-400" />
                                {product.name}
                            </span>
                            <input
                                type="number"
                                min="1"
                                step="1"
                                inputMode="numeric"
                                placeholder="0"
                                value={data.quantities[product.id] ?? ''}
                                onChange={(e) => setData('quantities', { ...data.quantities, [product.id]: e.target.value })}
                                aria-label={`Unidades de ${product.name}`}
                                className="w-28 py-2 px-3 text-sm text-right border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20 focus:border-slate-900 font-medium"
                            />
                        </label>
                    ))}

                    {errorList.length > 0 && (
                        <ul className="text-sm text-red-600 space-y-1 pt-1">
                            {errorList.map(([field, message]) => (
                                <li key={field}>{message}</li>
                            ))}
                        </ul>
                    )}
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
                        disabled={processing || nothingFilled}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        Registrar carga
                    </button>
                </div>
            </form>
        </Modal>
    );
}
