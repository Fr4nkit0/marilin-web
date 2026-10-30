import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2, X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import { FieldError } from '@/ui/Field';
import { HomeContainerType, HomePaymentMethod, HomeProduct } from '@/modules/Street/types';
import { money } from '@/lib/format';

interface Props {
    show: boolean;
    customerId: number;
    customerName: string;
    products: HomeProduct[];
    containerTypes: HomeContainerType[];
    paymentMethods: HomePaymentMethod[];
    onClose: () => void;
}

type ContainerRow = { container_type_id: string; type: 'DELIVERED' | 'RETURNED'; quantity: string };

type HomeSaleForm = {
    quantities: Record<number, string>;
    is_credit_sale: boolean;
    payment_method_id: string;
    notes: string;
    containers: ContainerRow[];
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600';


export default function HomeSaleModal({ show, customerId, customerName, products, containerTypes, paymentMethods, onClose }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors, transform } = useForm<HomeSaleForm>({
        quantities: {},
        is_credit_sale: false,
        payment_method_id: '',
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
    // A sale not on credit is paid on the spot, so it carries the payment method.
    transform((form) => ({
        lines: Object.entries(form.quantities)
            .filter(([, quantity]) => Number(quantity) > 0)
            .map(([productId, quantity]) => ({ product_id: Number(productId), quantity: Number(quantity) })),
        is_credit_sale: form.is_credit_sale,
        ...(form.is_credit_sale ? {} : { payment_method_id: form.payment_method_id ? Number(form.payment_method_id) : null }),
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

    const formErrors = Object.entries(errors).filter(([key]) => key !== 'quantities' && key !== 'payment_method_id');

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <ModalTitle className="text-lg font-semibold text-ink">Venta en casa</ModalTitle>
                        <p className="text-sm text-ink-muted mt-1">
                            {customerName}: no descuenta inventario de ninguna ruta. El precio sale del acuerdo vigente o
                            de la lista y no se puede cambiar acá.
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

                <div className="px-6 py-5 space-y-5">
                    <div className="space-y-2">
                        {/* One grid per row with the same columns, so every quantity input lines up whatever the product's name. */}
                        {products.map((product) => (
                            <div key={product.id} className="grid grid-cols-[minmax(0,1fr)_6rem] items-center gap-4">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-ink">{product.name}</p>
                                    <p className="text-xs text-ink-muted">
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
                                    className={`${inputClass} text-right`}
                                />
                            </div>
                        ))}
                        {products.length === 0 && <p className="text-sm text-ink-muted">No hay productos habilitados.</p>}
                    </div>

                    <div className="flex items-center justify-between border-t border-hairline pt-3">
                        <span className="text-sm font-medium text-ink-secondary">Total</span>
                        <span className="text-lg font-semibold text-ink">{money(total)}</span>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink-secondary">
                        <input
                            type="checkbox"
                            checked={data.is_credit_sale}
                            onChange={(e) => setData('is_credit_sale', e.target.checked)}
                        />
                        Venta a crédito
                    </label>

                    {!data.is_credit_sale && (
                        <label className="block">
                            <span className="text-sm font-medium text-ink-secondary">Medio de pago</span>
                            <select
                                value={data.payment_method_id}
                                onChange={(e) => setData('payment_method_id', e.target.value)}
                                className={`${inputClass} mt-1`}
                                required
                            >
                                <option value="">Elegí un medio de pago</option>
                                {paymentMethods.map((method) => (
                                    <option key={method.id} value={method.id}>
                                        {method.name}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.payment_method_id} className="mt-1" />
                        </label>
                    )}

                    <div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-ink-secondary">Envases (opcional)</span>
                            <button
                                type="button"
                                onClick={() =>
                                    setData('containers', [
                                        ...data.containers,
                                        { container_type_id: '', type: 'DELIVERED', quantity: '' },
                                    ])
                                }
                                className="inline-flex items-center gap-1 text-sm font-medium text-sky-600 dark:text-sky-300 hover:text-sky-500 cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                Agregar
                            </button>
                        </div>
                        {data.containers.map((row, index) => (
                            <div key={index} className="grid grid-cols-[minmax(0,1fr)_8rem_6rem_auto] items-center gap-2 mt-2">
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
                                    className={`${inputClass} text-right`}
                                />
                                <button
                                    type="button"
                                    aria-label="Quitar envase"
                                    onClick={() => setData('containers', data.containers.filter((_, i) => i !== index))}
                                    className="p-1.5 text-ink-subtle hover:text-red-600 dark:hover:text-red-300 cursor-pointer"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        ))}
                    </div>

                    <label className="block">
                        <span className="text-sm font-medium text-ink-secondary">Notas (opcional)</span>
                        <textarea
                            value={data.notes}
                            maxLength={500}
                            rows={2}
                            onChange={(e) => setData('notes', e.target.value)}
                            className={`${inputClass} mt-1`}
                        />
                    </label>

                    {formErrors.length > 0 && (
                        <div className="space-y-1">
                            {formErrors.map(([key, message]) => (
                                <FieldError key={key} message={message} />
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
                        disabled={processing || total <= 0 || (!data.is_credit_sale && !data.payment_method_id)}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                    >
                        Registrar venta
                    </button>
                </div>
            </form>
        </Modal>
    );
}
