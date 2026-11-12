import { FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft } from 'lucide-react';
import AdminLayout from '@/layouts/AdminLayout';
import { PageHeader } from '@/ui/PageHeader';
import Button from '@/ui/Button';
import DataTable, { Column } from '@/ui/DataTable';
import { Checkbox, Field, FieldError, Select } from '@/ui/Field';
import type { DriverOption, TransferCustomer } from './types';
import { NO_NEIGHBORHOOD, allShownSelected, neighborhoodsOf, shownCustomers, toggleCustomer, toggleShown } from './transferSelection';

interface Props {
    driver: DriverOption;
    customers: TransferCustomer[];
    drivers: DriverOption[];
}

/**
 * "Transferir cartera": the driver's customers go to another driver. The page stays on the same driver after each
 * transfer, so the portfolio is split by repeating it (some customers to one driver, some to another).
 */
export default function Transfer({ driver, customers, drivers }: Props) {
    const [neighborhood, setNeighborhood] = useState('');
    const form = useForm({ to_driver_id: '', customer_ids: [] as number[] });

    const shown = shownCustomers(customers, neighborhood);
    const selected = form.data.customer_ids;
    const customerError = form.errors.customer_ids ?? Object.entries(form.errors).find(([key]) => key.startsWith('customer_ids.'))?.[1];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('customers.transfer.store', driver.id), {
            preserveScroll: true,
            onSuccess: () => form.setData('customer_ids', []),
        });
    };

    const columns: Column<TransferCustomer>[] = [
        {
            header: (
                <Checkbox
                    checked={allShownSelected(selected, shown)}
                    onChange={() => form.setData('customer_ids', toggleShown(selected, shown))}
                    disabled={shown.length === 0}
                    aria-label={neighborhood === '' ? 'Seleccionar todos' : `Seleccionar todos de ${neighborhood}`}
                />
            ),
            className: 'w-10',
            cell: (row) => (
                <Checkbox
                    checked={selected.includes(row.id)}
                    onChange={() => form.setData('customer_ids', toggleCustomer(selected, row.id))}
                    aria-label={`Seleccionar ${row.name}`}
                />
            ),
        },
        {
            header: 'Cliente',
            cell: (row) => (
                <Link href={route('customers.show', row.id)} className="font-medium text-ink hover:text-sky-600 dark:hover:text-sky-300 transition-colors">
                    {row.name}
                </Link>
            ),
        },
        { header: 'Dirección', cell: (row) => <span className="text-ink-secondary">{row.street ?? 'Sin dirección'}</span> },
        { header: 'Barrio', cell: (row) => <span className="text-ink-secondary">{row.neighborhood ?? NO_NEIGHBORHOOD}</span> },
    ];

    return (
        <AdminLayout header={<span className="text-sm font-medium text-ink-muted">Repartidores</span>}>
            <Head title={`Transferir cartera de ${driver.name}`} />

            <div className="mb-4">
                <Link
                    href={route('drivers.show', driver.id)}
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-muted hover:text-ink transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Volver a {driver.name}
                </Link>
            </div>

            <PageHeader
                title={`Transferir cartera de ${driver.name}`}
                description={
                    customers.length === 1
                        ? 'Le queda 1 cliente. Elegilo y elegí el repartidor que lo recibe.'
                        : `Le quedan ${customers.length} clientes. Elegí cuáles pasan y a qué repartidor; repetilo hasta repartir toda la cartera.`
                }
                breadcrumbs={[
                    { label: 'Repartidores', href: route('drivers.index') },
                    { label: driver.name, href: route('drivers.show', driver.id) },
                    { label: 'Transferir cartera' },
                ]}
            />

            <form onSubmit={submit} className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 p-4 mb-4 grid grid-cols-1 gap-4 sm:grid-cols-12 sm:items-end">
                <div className="sm:col-span-4">
                    <Field label="Barrio">
                        <Select value={neighborhood} onChange={(e) => setNeighborhood(e.target.value)}>
                            <option value="">Todos los barrios</option>
                            {neighborhoodsOf(customers).map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <div className="sm:col-span-4">
                    <Field label="Pasan a" error={form.errors.to_driver_id}>
                        <Select
                            value={form.data.to_driver_id}
                            invalid={Boolean(form.errors.to_driver_id)}
                            onChange={(e) => form.setData('to_driver_id', e.target.value)}
                        >
                            <option value="">Elegí un repartidor</option>
                            {drivers.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <div className="sm:col-span-4 sm:flex sm:justify-end">
                    <Button type="submit" processing={form.processing} disabled={selected.length === 0 || form.data.to_driver_id === ''}>
                        <ArrowRightLeft className="w-4 h-4" />
                        {selected.length === 1 ? 'Transferir 1 cliente' : `Transferir ${selected.length} clientes`}
                    </Button>
                </div>
            </form>

            <FieldError message={customerError} className="mb-3" />

            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden">
                <DataTable
                    columns={columns}
                    rows={shown}
                    rowKey={(row) => row.id}
                    empty={customers.length === 0 ? `${driver.name} no tiene clientes.` : 'No hay clientes en este barrio.'}
                />
            </div>
        </AdminLayout>
    );
}
