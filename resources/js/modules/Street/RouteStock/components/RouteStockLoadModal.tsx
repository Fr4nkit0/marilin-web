import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Package, X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import { FieldError } from '@/ui/Field';

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

export default function RouteStockLoadModal({ routeId, driverName, isFirstLoad, products, onClose }: Props) {
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
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <ModalTitle className="text-lg font-semibold text-ink">
                            {isFirstLoad ? 'Carga inicial del inventario de ruta' : 'Recarga del inventario de ruta'}
                        </ModalTitle>
                        <p className="text-sm text-ink-muted mt-1">
                            {driverName}: lo que se carga se suma al stock y llega a la app en la próxima
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

                <div className="px-6 py-5 space-y-3">
                    {products.map((product) => (
                        <label key={product.id} className="flex items-center justify-between gap-4">
                            <span className="flex items-center gap-2 text-sm font-medium text-ink">
                                <Package className="w-4 h-4 text-ink-subtle" />
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
                                className="w-28 py-2 px-3 text-sm text-right border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 font-medium"
                            />
                        </label>
                    ))}

                    {errorList.length > 0 && (
                        <div className="space-y-1 pt-1">
                            {errorList.map(([field, message]) => (
                                <FieldError key={field} message={message} />
                            ))}
                        </div>
                    )}
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
                        disabled={processing || nothingFilled}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                    >
                        Registrar carga
                    </button>
                </div>
            </form>
        </Modal>
    );
}
