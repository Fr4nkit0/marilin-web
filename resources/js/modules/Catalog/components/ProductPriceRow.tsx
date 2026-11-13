import React from 'react';
import { useForm } from '@inertiajs/react';
import { FieldError } from '@/ui/Field';
import { Save } from 'lucide-react';
import { StatusBadge } from '@/ui/Badge';
import { ProductItem } from '@/modules/Catalog/types';

interface Props {
    product: ProductItem;
}

export default function ProductPriceRow({ product }: Props) {
    const { data, setData, patch, processing, errors, isDirty } = useForm({ price: product.price });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('products.price', product.id), { preserveScroll: true });
    };

    return (
        <tr className="hover:bg-canvas-soft transition-colors max-sm:border-b max-sm:border-hairline max-sm:last:border-0">
            <td data-primary className="px-6 py-4">
                <div className="font-medium text-ink">{product.name}</div>
                {product.description && <div className="text-xs font-normal text-ink-muted mt-0.5">{product.description}</div>}
            </td>
            <td data-label="Estado" className="px-6 py-4 whitespace-nowrap">
                <StatusBadge status={product.enabled ? 'finalizado' : 'cancelado'} label={product.enabled ? 'Activo' : 'Inactivo'} />
            </td>
            {/* On a phone the form drops below its label when the line is too narrow for both. */}
            <td data-label="Precio de lista" className="px-6 py-4 max-sm:flex-wrap">
                <form onSubmit={submit} noValidate className="flex items-start justify-end gap-2 max-sm:ml-auto">
                    <div>
                        <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-subtle">$</span>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                aria-label={`Precio de lista de ${product.name}`}
                                className="w-32 sm:w-36 py-2 pl-7 pr-3 text-sm text-right border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 font-medium"
                            />
                        </div>
                        <FieldError message={errors.price} className="mt-1" />
                    </div>
                    <button
                        type="submit"
                        disabled={processing || !isDirty}
                        className="inline-flex min-h-11 sm:min-h-0 items-center gap-1.5 px-3 py-2 rounded-full bg-sky-600 text-white hover:bg-sky-700 font-medium transition text-xs shadow-sm cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                    >
                        <Save className="w-3.5 h-3.5" />
                        Guardar
                    </button>
                </form>
            </td>
        </tr>
    );
}
