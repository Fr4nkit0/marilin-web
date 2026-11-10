import React, { useState } from 'react';
import { useListFilters } from '@/lib/useListFilters';
import { listSort } from '@/lib/sort';
import SortableTh from '@/ui/SortableTh';
import Pagination from '@/ui/Pagination';
import type { PaginatedData } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/layouts/AdminLayout';
import { DebtBadge } from '@/ui/Badge';
import { PageHeader } from '@/ui/PageHeader';
import { Field, FieldError, Input } from '@/ui/Field';
import Button from '@/ui/Button';
import Modal from '@/ui/Modal';
import PreferenceRowsField from './components/PreferenceRowsField';
import CustomerFormFields, { FormSection } from './components/CustomerFormFields';
import CustomerModalHeader from './components/CustomerModalHeader';
import InitialConditionsFields from './components/InitialConditionsFields';
import { PreferenceInput } from './types';
import {
    Search,
    Plus,
    Eye,
    MapPin,
    Phone,
    Package,
    SlidersHorizontal,
    Building2,
    Save,
} from 'lucide-react';

interface CustomerAddress {
    id: number;
    street: string;
    apartment?: string;
    floor?: string;
    visual_reference?: string;
    neighborhood?: string | null;
    is_primary: boolean;
}

interface ContainerBalance {
    id: number;
    balance: number;
    container_type?: {
        name: string;
        code: string;
    };
}

interface CustomerItem {
    id: number;
    name: string;
    phone_number?: string;
    driver?: {
        id: number;
        user?: {
            name: string;
        };
    };
    repartidor?: {
        id: number;
        user?: {
            name: string;
        };
    };
    primary_address?: CustomerAddress;
    balance?: {
        current_balance: string | number;
    };
    container_balances?: ContainerBalance[];
}

interface Props {
    customers: PaginatedData<CustomerItem>;
    filters: {
        search?: string;
        repartidor_id?: string;
        debt_status?: string;
        sort?: string | null;
        direction?: string | null;
    };
    repartidores: { id: number; name: string }[];
    neighborhoods: string[];
    containerTypes: { id: number; description: string }[];
}

const NEIGHBORHOOD_OPTIONS = 'neighborhood-options';

export default function CustomersIndex({ customers, filters, repartidores, neighborhoods, containerTypes }: Props) {
    const filter = useListFilters(
        'customers.index',
        {
            search: filters.search || '',
            repartidor_id: filters.repartidor_id || '',
            debt_status: filters.debt_status || 'all',
            sort: filters.sort || '',
            direction: filters.direction || '',
        },
        { search: '', repartidor_id: '', debt_status: 'all', sort: '', direction: '' },
    );
    const sort = listSort(filter.values, filter.setMany);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<CustomerItem | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        phone_number: '',
        repartidor_id: '',
        street: '',
        floor: '',
        apartment: '',
        visual_reference: '',
        neighborhood: '',
        initial_balance: '0',
        // Keyed by container type id, one count per enabled type.
        initial_containers: {} as Record<string, string>,
        // Delivery preferences stored with the customer.
        preferences: [] as PreferenceInput[],
    });

    const editForm = useForm({
        name: '',
        phone_number: '',
        repartidor_id: '',
        street: '',
        floor: '',
        apartment: '',
        visual_reference: '',
        neighborhood: '',
        // New delivery preferences only; the existing ones are changed from the customer page.
        preferences: [] as PreferenceInput[],
    });

    const isFiltered = Boolean(filters.search || filters.repartidor_id || (filters.debt_status && filters.debt_status !== 'all'));

    const handleCreateCustomer = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('customers.store'), {
            onSuccess: () => {
                setIsCreateModalOpen(false);
                reset();
            },
        });
    };

    const handleOpenEditModal = (c: CustomerItem) => {
        setEditingCustomer(c);
        const assignedDriverId = c.driver?.id ?? c.repartidor?.id;
        editForm.setData({
            name: c.name,
            phone_number: c.phone_number || '',
            repartidor_id: assignedDriverId ? String(assignedDriverId) : '',
            street: c.primary_address?.street || '',
            floor: c.primary_address?.floor || '',
            apartment: c.primary_address?.apartment || '',
            visual_reference: c.primary_address?.visual_reference || '',
            neighborhood: c.primary_address?.neighborhood || '',
            preferences: [],
        });
        editForm.clearErrors();
    };

    const handleUpdateCustomer = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingCustomer) return;
        editForm.put(route('customers.update', editingCustomer.id), {
            onSuccess: () => {
                setEditingCustomer(null);
            },
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        filter.submit();
    };

    return (
        <AdminLayout
            header={
                <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-ink-muted">
                        Clientes
                    </span>
                </div>
            }
        >
            <Head title="Clientes" />

            <div className="mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-ink tracking-tight">
                        Clientes
                    </h1>
                    <p className="text-sm font-medium text-ink-muted mt-1 tracking-wide">
                        {isFiltered
                            ? customers.total === 1
                                ? '1 cliente coincide con los filtros.'
                                : `${customers.total} clientes coinciden con los filtros.`
                            : `Total de ${customers.total} clientes registrados en el sistema.`}
                    </p>
                </div>
                <button
                    onClick={() => setIsCreateModalOpen(true)}
                    className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 transition shadow-sm cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                >
                    <Plus className="w-4 h-4" />
                    Nuevo Cliente
                </button>
            </div>

            {/* Filters Bar */}
            <div className="bg-canvas/80 backdrop-blur-md rounded-2xl p-4 mb-6 shadow-sm border border-hairline/60 ">
                <form onSubmit={handleSearch} className="grid grid-cols-1 gap-4 sm:grid-cols-12 items-center">
                    {/* Search Input */}
                    <div className="sm:col-span-6 relative">
                        <Search className="w-4 h-4 text-ink-subtle absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            value={filter.values.search}
                            onChange={(e) => filter.draft('search', e.target.value)}
                            aria-label="Buscar cliente"
                            placeholder="Buscar por nombre, teléfono o dirección..."
                            className="w-full pl-9 pr-4 py-2 text-sm border border-hairline/60 bg-canvas text-ink placeholder-ink-subtle rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 font-medium"
                        />
                    </div>

                    {/* Repartidor Filter */}
                    <div className="sm:col-span-3">
                        <select
                            value={filter.values.repartidor_id}
                            onChange={(e) => filter.set('repartidor_id', e.target.value)}
                            aria-label="Repartidor"
                            className="w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink placeholder-ink-subtle rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 font-medium"
                        >
                            <option value="">Todos los Repartidores</option>
                            {repartidores.map((rep) => (
                                <option key={rep.id} value={rep.id}>
                                    {rep.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Debt Filter */}
                    <div className="sm:col-span-3">
                        <select
                            value={filter.values.debt_status}
                            onChange={(e) => filter.set('debt_status', e.target.value)}
                            aria-label="Estado de deuda"
                            className="w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink placeholder-ink-subtle rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 font-medium"
                        >
                            <option value="all">Todos los Estados</option>
                            <option value="with_debt">Con Deuda Pendiente</option>
                            <option value="clean">Al Día (Sin Deuda)</option>
                        </select>
                    </div>
                </form>
            </div>

            {/* Customers Table */}
            <div className="bg-canvas rounded-2xl shadow-sm border border-hairline/60 overflow-hidden ">
                <div className="table-scroll">
                    <table className="w-full text-left text-sm whitespace-nowrap">
                        <thead className="bg-canvas/80 backdrop-blur-md border-b border-hairline text-ink-muted">
                            <tr>
                                <SortableTh direction={sort.directionOf('name')} onSort={() => sort.toggle('name')} className="px-4 py-4 text-xs font-medium text-ink tracking-wide">
                                    Cliente
                                </SortableTh>
                                <SortableTh direction={sort.directionOf('address')} onSort={() => sort.toggle('address')} className="px-4 py-4 text-xs font-medium text-ink tracking-wide">
                                    Dirección
                                </SortableTh>
                                <SortableTh direction={sort.directionOf('driver')} onSort={() => sort.toggle('driver')} className="px-4 py-4 text-xs font-medium text-ink tracking-wide">
                                    Repartidor
                                </SortableTh>
                                <SortableTh direction={sort.directionOf('containers')} onSort={() => sort.toggle('containers')} align="center" className="px-4 py-4 text-xs font-medium text-ink tracking-wide">
                                    Envases
                                </SortableTh>
                                <SortableTh direction={sort.directionOf('balance')} onSort={() => sort.toggle('balance')} align="right" className="px-4 py-4 text-xs font-medium text-ink tracking-wide">
                                    Cuenta Corriente
                                </SortableTh>
                                <th className="px-4 py-4 text-xs font-medium text-ink tracking-wide text-right"><span className="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody className="">
                            {customers.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-6 py-10 text-center text-ink-muted text-sm">
                                        <span className="table-empty">No se encontraron clientes con los filtros aplicados.</span>
                                    </td>
                                </tr>
                            ) : (
                                customers.data.map((c) => {
                                    const balanceNum = Number(c.balance?.current_balance || 0);
                                    const totalContainers = c.container_balances?.reduce(
                                        (acc, cb) => acc + cb.balance,
                                        0
                                    ) || 0;

                                    return (
                                        <tr key={c.id} className="hover:bg-canvas-soft transition-colors">
                                            {/* Name & Phone */}
                                            <td className="px-4 py-4 whitespace-normal min-w-[9rem]">
                                                <Link href={route('customers.show', c.id)} className="font-medium text-ink hover:text-sky-700 dark:hover:text-sky-300 transition">
                                                    {c.name}
                                                </Link>
                                                {c.phone_number && (
                                                    <div className="flex items-center gap-1.5 text-ink-muted text-xs mt-1">
                                                        <Phone className="w-3.5 h-3.5" />
                                                        <span>{c.phone_number}</span>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Primary Address */}
                                            <td className="px-4 py-4 text-ink-secondary">
                                                {c.primary_address ? (
                                                    <div className="flex items-start gap-2 min-w-[12rem] max-w-[16rem]">
                                                        <MapPin className="w-4 h-4 text-ink-subtle shrink-0 mt-0.5" />
                                                        <span className="whitespace-normal">
                                                            {c.primary_address.street}
                                                            {c.primary_address.floor && (
                                                                <span className="text-ink-subtle">
                                                                    , Piso {c.primary_address.floor}
                                                                </span>
                                                            )}
                                                            {c.primary_address.apartment && (
                                                                <span className="text-ink-subtle">
                                                                    , Depto/Lote {c.primary_address.apartment}
                                                                </span>
                                                            )}
                                                            {c.primary_address.neighborhood && (
                                                                <span className="block text-xs text-ink-subtle">
                                                                    {c.primary_address.neighborhood}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-ink-subtle italic">Sin dirección</span>
                                                )}
                                            </td>

                                            {/* Repartidor / Driver */}
                                            <td className="px-4 py-4">
                                                {(c.driver?.user || c.repartidor?.user) ? (
                                                    <span className="inline-flex items-center gap-2 font-medium text-ink">
                                                        <div className="w-6 h-6 rounded-lg bg-surface-1 text-ink-secondary flex items-center justify-center text-xs">
                                                            {(c.driver?.user?.name || c.repartidor?.user?.name || '').charAt(0)}
                                                        </div>
                                                        {c.driver?.user?.name || c.repartidor?.user?.name}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-1 text-ink-secondary">Sin asignar</span>
                                                )}
                                            </td>

                                            {/* Containers Balance */}
                                            <td className="px-4 py-4 text-center">
                                                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-1 text-ink-secondary font-medium text-xs">
                                                    <Package className="w-3.5 h-3.5" />
                                                    {totalContainers} unid
                                                </span>
                                            </td>

                                            {/* Debt Status & Balance */}
                                            <td className="px-4 py-4 text-right">
                                                <DebtBadge balance={balanceNum} />
                                            </td>

                                            {/* Actions: icon buttons, so the column stays narrow and the table fits a laptop screen. */}
                                            <td className="px-4 py-4 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleOpenEditModal(c)}
                                                        aria-label={`Editar datos de ${c.name}`}
                                                        title="Editar datos del cliente"
                                                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-hairline bg-canvas text-ink-secondary hover:bg-canvas-soft transition cursor-pointer shadow-sm"
                                                    >
                                                        <SlidersHorizontal className="w-4 h-4" />
                                                    </button>
                                                    <Link
                                                        href={route('customers.show', c.id)}
                                                        aria-label={`Ver ficha de ${c.name}`}
                                                        title="Ver ficha"
                                                        className="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-600 text-white hover:bg-sky-700 transition shadow-sm dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination page={customers} />
            </div>

            <datalist id={NEIGHBORHOOD_OPTIONS}>
                {neighborhoods.map((name) => (
                    <option key={name} value={name} />
                ))}
            </datalist>

            <Modal show={isCreateModalOpen} onClose={() => setIsCreateModalOpen(false)} maxWidth="lg">
                <CustomerModalHeader
                    icon={Building2}
                    title="Nuevo cliente"
                    description="Datos, dirección de entrega y lo que ya debe o tiene en comodato."
                    onClose={() => setIsCreateModalOpen(false)}
                />

                <form onSubmit={handleCreateCustomer} noValidate className="flex min-h-0 flex-1 flex-col">
                    {/* Only the fields scroll: the title and the buttons stay in view on a short screen. */}
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-6">
                        <CustomerFormFields
                            idPrefix="create"
                            data={data}
                            setData={(key, value) => setData(key, value)}
                            errors={errors}
                            repartidores={repartidores}
                            neighborhoodList={NEIGHBORHOOD_OPTIONS}
                        />

                        <FormSection title="Condiciones iniciales">
                            <p className="-mt-2 text-xs text-ink-muted">
                                Para un cliente que ya existía antes del sistema: su deuda y los envases que tiene en su casa.
                            </p>
                            <InitialConditionsFields
                                balance={data.initial_balance}
                                containers={data.initial_containers}
                                containerTypes={containerTypes}
                                onBalanceChange={(value) => setData('initial_balance', value)}
                                onContainersChange={(containers) => setData('initial_containers', containers)}
                                errors={errors as Record<string, string>}
                            />
                        </FormSection>

                        <FormSection title="Días y horarios de entrega">
                            <PreferenceRowsField
                                idPrefix="create"
                                rows={data.preferences}
                                onChange={(rows) => setData('preferences', rows)}
                                errors={errors as Record<string, string>}
                            />
                        </FormSection>
                    </div>

                    <div className="shrink-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                        <Button variant="secondary" onClick={() => setIsCreateModalOpen(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" processing={processing}>
                            <Save className="w-4 h-4" />
                            {processing ? 'Guardando…' : 'Crear cliente'}
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal show={editingCustomer !== null} onClose={() => setEditingCustomer(null)} maxWidth="lg">
                <CustomerModalHeader
                    icon={SlidersHorizontal}
                    title="Editar cliente"
                    description="Nombre, teléfono, repartidor o dirección de entrega."
                    onClose={() => setEditingCustomer(null)}
                />

                <form onSubmit={handleUpdateCustomer} noValidate className="flex min-h-0 flex-1 flex-col">
                    {/* Only the fields scroll: the title and the buttons stay in view on a short screen. */}
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-6">
                        <CustomerFormFields
                            idPrefix="edit"
                            data={editForm.data}
                            setData={(key, value) => editForm.setData(key, value)}
                            errors={editForm.errors}
                            repartidores={repartidores}
                            neighborhoodList={NEIGHBORHOOD_OPTIONS}
                        />

                        <FormSection title="Agregar días y horarios de entrega">
                            <p className="-mt-2 text-xs text-ink-muted">
                                Los que ya tiene se editan o quitan desde la ficha del cliente.
                            </p>
                            <PreferenceRowsField
                                idPrefix="edit"
                                rows={editForm.data.preferences}
                                onChange={(rows) => editForm.setData('preferences', rows)}
                                errors={editForm.errors as Record<string, string>}
                            />
                        </FormSection>
                    </div>

                    <div className="shrink-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                        <Button variant="secondary" onClick={() => setEditingCustomer(null)}>
                            Cancelar
                        </Button>
                        <Button type="submit" processing={editForm.processing}>
                            <Save className="w-4 h-4" />
                            {editForm.processing ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
