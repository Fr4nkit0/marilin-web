import React from 'react';
import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductPriceRow from '@/Components/ProductPriceRow';
import { ProductItem } from '@/types/product';

interface Props {
    products: ProductItem[];
}

export default function Index({ products }: Props) {
    return (
        <AdminLayout header={<span className="text-sm font-medium text-slate-500">Productos</span>}>
            <Head title="Productos - Marilin" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900 tracking-tight">Productos</h1>
                <p className="text-sm font-medium text-slate-500 mt-1 tracking-wide">
                    El precio de lista se usa cuando el cliente no tiene un acuerdo vigente. Los cambios llegan a la app
                    en la próxima sincronización.
                </p>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-white/80 backdrop-blur-md border-b border-gray-100 text-slate-500">
                            <tr>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Producto</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide">Estado</th>
                                <th className="px-6 py-4 text-xs font-medium text-slate-900 tracking-wide text-right">
                                    Precio de lista
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="px-6 py-10 text-center text-slate-500 text-sm">
                                        No hay productos cargados.
                                    </td>
                                </tr>
                            ) : (
                                products.map((product) => <ProductPriceRow key={product.id} product={product} />)
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
