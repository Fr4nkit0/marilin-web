import React from 'react';
import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { StatusBadge } from '@/Components/StitchUi';
import { ProductItem } from '@/types/product';

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
        <tr className="hover:bg-slate-50 transition-colors">
            <td className="px-6 py-4">
                <div className="font-medium text-slate-900">{product.name}</div>
                {product.description && <div className="text-xs text-slate-500 mt-0.5">{product.description}</div>}
            </td>
            <td className="px-6 py-4 whitespace-nowrap">
                <StatusBadge status={product.enabled ? 'finalizado' : 'cancelado'} label={product.enabled ? 'Activo' : 'Inactivo'} />
            </td>
            <td className="px-6 py-4">
                <form onSubmit={submit} className="flex items-start justify-end gap-2">
                    <div>
                        <div className="relative">
                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">$</span>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                aria-label={`Precio de lista de ${product.name}`}
                                className="w-36 py-2 pl-7 pr-3 text-sm text-right border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 font-medium"
                            />
                        </div>
                        {errors.price && <p className="text-xs text-red-600 mt-1">{errors.price}</p>}
                    </div>
                    <button
                        type="submit"
                        disabled={processing || !isDirty}
                        className="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-blue-600 text-white hover:bg-blue-700 font-medium transition text-xs shadow-sm cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        <Save className="w-3.5 h-3.5" />
                        Guardar
                    </button>
                </form>
            </td>
        </tr>
    );
}
