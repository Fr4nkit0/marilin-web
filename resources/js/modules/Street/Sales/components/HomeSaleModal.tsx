import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import Modal from '@/ui/Modal';
import { HomeContainerType, HomeProduct } from '@/modules/Street/types';
import { money } from '@/lib/format';

interface Props {
    show: boolean;
    customerId: number;
    customerName: string;
    products: HomeProduct[];
    containerTypes: HomeContainerType[];
    onClose: () => void;
}

type ContainerRow = { container_type_id: string; type: 'DELIVERED' | 'RETURNED'; quantity: string };

type HomeSaleForm = {
    quantities: Record<number, string>;
    is_credit_sale: boolean;
    notes: string;
    containers: ContainerRow[];
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20 focus:border-slate-900';


export default function HomeSaleModal({ show, customerId, customerName, products, containerTypes, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<HomeSaleForm>({
        quantities: {},
        is_credit_sale: false,
        notes: '',
        containers: [],
    });

    useEffect(() => {
        if (!show) {
            reset();
            clearErrors();
        }
    }, [show]);

    // Only the product and the quantity travel: the server resolves every price.
    transform((form) => ({
        lines: Object.entries(form.quantities)
            .filter(([, quantity]) => Number(quantity) > 0)
            .map(([productId, quantity]) => ({ product_id: Number(productId), quantity: Number(quantity) })),
        is_credit_sale: form.is_credit_sale,
        notes: form.notes.trim() || null,
        containers: form.containers
            .filter((row) => row.container_type_id && Number(row.quantity) > 0)
            .map((row) => ({ container_type_id: Number(row.container_type_id), type: row.type, quantity: Number(row.quantity) })),
    }));

    const total = products.reduce((sum, product) => sum + product.unitPrice * Number(data.quantities[product.id] || 0), 0);

    const updateContainer = (index: number, changes: Partial<ContainerRow>) =>
        setData(
            'containers',
            data.containers.map((row, i) => (i === index ? { ...row, ...changes } : row)),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('customers.home-sales.store', customerId), { preserveScroll: true, onSuccess: onClose });
    };

    const formErrors = Object.entries(errors).filter(([key]) => key !== 'quantities');

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit}>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Venta en casa</h2>
                        <p className="text-sm text-slate-500 mt-1">
                            {customerName}: no descuenta inventario de ninguna ruta. El precio sale del acuerdo vigente o
                            de la lista y no se puede cambiar acá.
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

                <div className="px-6 py-5 space-y-5">
                    <div className="space-y-2">
                        {products.map((product) => (
                            <div key={product.id} className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-slate-900">{product.name}</p>
                                    <p className="text-xs text-slate-500">
                                        {money(product.unitPrice)} · {product.source === 'agreement' ? 'Acuerdo vigente' : 'Precio de lista'}
                                    </p>
                                </div>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    inputMode="numeric"
                                    aria-label={`Cantidad de ${product.name}`}
                                    value={data.quantities[product.id] ?? ''}
                                    onChange={(e) => setData('quantities', { ...data.quantities, [product.id]: e.target.value })}
                                    className={`${inputClass} w-24 text-right`}
                                />
                            </div>
                        ))}
                        {products.length === 0 && <p className="text-sm text-slate-500">No hay productos habilitados.</p>}
                    </div>

                    <div className="flex items-center justify-between border-t border-gray-100 pt-3">
                        <span className="text-sm font-medium text-slate-700">Total</span>
                        <span className="text-lg font-semibold text-slate-900">{money(total)}</span>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            checked={data.is_credit_sale}
                            onChange={(e) => setData('is_credit_sale', e.target.checked)}
                        />
                        Venta a crédito
                    </label>

                    <div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-slate-700">Envases (opcional)</span>
                            <button
                                type="button"
                                onClick={() =>
                                    setData('containers', [
                                        ...data.containers,
                                        { container_type_id: '', type: 'DELIVERED', quantity: '' },
                                    ])
                                }
                                className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500 cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                Agregar
                            </button>
                        </div>
                        {data.containers.map((row, index) => (
                            <div key={index} className="flex items-center gap-2 mt-2">
                                <select
                                    aria-label="Tipo de envase"
                                    value={row.container_type_id}
                                    onChange={(e) => updateContainer(index, { container_type_id: e.target.value })}
                                    className={inputClass}
                                >
                                    <option value="">Elegí un envase</option>
                                    {containerTypes.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.description}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    aria-label="Movimiento"
                                    value={row.type}
                                    onChange={(e) => updateContainer(index, { type: e.target.value as ContainerRow['type'] })}
                                    className={inputClass}
                                >
                                    <option value="DELIVERED">Se lleva</option>
                                    <option value="RETURNED">Devuelve</option>
                                </select>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    inputMode="numeric"
                                    aria-label="Cantidad de envases"
                                    value={row.quantity}
                                    onChange={(e) => updateContainer(index, { quantity: e.target.value })}
                                    className={`${inputClass} w-24 text-right`}
                                />
                                <button
                                    type="button"
                                    aria-label="Quitar envase"
                                    onClick={() => setData('containers', data.containers.filter((_, i) => i !== index))}
                                    className="p-1.5 text-slate-400 hover:text-red-600 cursor-pointer"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        ))}
                    </div>

                    <label className="block">
                        <span className="text-sm font-medium text-slate-700">Notas (opcional)</span>
                        <textarea
                            value={data.notes}
                            maxLength={500}
                            rows={2}
                            onChange={(e) => setData('notes', e.target.value)}
                            className={`${inputClass} mt-1`}
                        />
                    </label>

                    {formErrors.length > 0 && (
                        <ul className="space-y-1">
                            {formErrors.map(([key, message]) => (
                                <li key={key} className="text-sm text-red-600">
                                    {message}
                                </li>
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
                        disabled={processing || total <= 0}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        Registrar venta
                    </button>
                </div>
            </form>
        </Modal>
    );
}
