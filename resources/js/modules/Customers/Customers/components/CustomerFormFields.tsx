import React from 'react';
import { formatPhone } from '@/lib/format';
import { Field, Input, Select } from '@/ui/Field';

/** What the new-customer and edit-customer forms share: who the customer is and where the truck stops. */
export interface CustomerFields {
    name: string;
    phone_number: string;
    repartidor_id: string;
    street: string;
    floor: string;
    apartment: string;
    visual_reference: string;
    neighborhood: string;
}

interface Props {
    /** Keeps the ids of the two forms apart ("create-name", "edit-name"). */
    idPrefix: string;
    data: CustomerFields;
    setData: (key: keyof CustomerFields, value: string) => void;
    errors: Partial<Record<keyof CustomerFields, string>>;
    repartidores: { id: number; name: string }[];
    /** The datalist with the catalog neighborhoods, rendered once by the page. */
    neighborhoodList: string;
}

export function FormSection({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="space-y-4 pt-6 border-t border-hairline first:pt-0 first:border-t-0">
            <h3 className="text-sm font-semibold text-ink">{title}</h3>
            {children}
        </section>
    );
}

function Required({ children }: { children: React.ReactNode }) {
    return (
        <>
            {children}
            <span className="text-rose-600 dark:text-rose-300" aria-hidden="true">
                {' '}
                *
            </span>
        </>
    );
}

export default function CustomerFormFields({ idPrefix, data, setData, errors, repartidores, neighborhoodList }: Props) {
    const id = (field: keyof CustomerFields) => `${idPrefix}-${field.replace(/_/g, '-')}`;

    return (
        <>
            <FormSection title="Datos del cliente">
                <Field label={<Required>Nombre o razón social</Required>} htmlFor={id('name')} error={errors.name}>
                    <Input
                        id={id('name')}
                        required
                        value={data.name}
                        invalid={Boolean(errors.name)}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Ej: Sanatorio Los Lapachos S.A."
                    />
                </Field>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label="Teléfono" htmlFor={id('phone_number')} error={errors.phone_number}>
                        <Input
                            id={id('phone_number')}
                            type="tel"
                            inputMode="tel"
                            value={data.phone_number}
                            invalid={Boolean(errors.phone_number)}
                            onChange={(e) => setData('phone_number', formatPhone(e.target.value))}
                            placeholder="Ej: 387 422-3000"
                            className="tabular-nums"
                        />
                    </Field>

                    <Field label="Repartidor asignado" htmlFor={id('repartidor_id')} error={errors.repartidor_id}>
                        <Select
                            id={id('repartidor_id')}
                            value={data.repartidor_id}
                            invalid={Boolean(errors.repartidor_id)}
                            onChange={(e) => setData('repartidor_id', e.target.value)}
                        >
                            <option value="">Sin asignar</option>
                            {repartidores.map((rep) => (
                                <option key={rep.id} value={rep.id}>
                                    {rep.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
            </FormSection>

            <FormSection title="Dirección principal">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Field label={<Required>Calle y altura</Required>} htmlFor={id('street')} error={errors.street}>
                        <Input
                            id={id('street')}
                            required
                            value={data.street}
                            invalid={Boolean(errors.street)}
                            onChange={(e) => setData('street', e.target.value)}
                            placeholder="Ej: Belgrano 340"
                        />
                    </Field>

                    <Field label="Barrio" htmlFor={id('neighborhood')} error={errors.neighborhood}>
                        <Input
                            id={id('neighborhood')}
                            list={neighborhoodList}
                            autoComplete="off"
                            value={data.neighborhood}
                            invalid={Boolean(errors.neighborhood)}
                            onChange={(e) => setData('neighborhood', e.target.value)}
                            placeholder="Buscá, ej: Tres Cerritos"
                        />
                    </Field>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-[1fr_1fr_2fr] gap-4">
                    <Field label="Piso" htmlFor={id('floor')} error={errors.floor}>
                        <Input
                            id={id('floor')}
                            maxLength={20}
                            value={data.floor}
                            invalid={Boolean(errors.floor)}
                            onChange={(e) => setData('floor', e.target.value)}
                            placeholder="PB, 3"
                        />
                    </Field>

                    <Field label="Depto/Lote" htmlFor={id('apartment')} error={errors.apartment}>
                        <Input
                            id={id('apartment')}
                            value={data.apartment}
                            invalid={Boolean(errors.apartment)}
                            onChange={(e) => setData('apartment', e.target.value)}
                            placeholder="4B, Lote 12"
                        />
                    </Field>

                    <div className="col-span-2 sm:col-span-1">
                        <Field label="Referencia visual" htmlFor={id('visual_reference')} error={errors.visual_reference}>
                            <Input
                                id={id('visual_reference')}
                                value={data.visual_reference}
                                invalid={Boolean(errors.visual_reference)}
                                onChange={(e) => setData('visual_reference', e.target.value)}
                                placeholder="Ej: Frente a Plaza Belgrano"
                            />
                        </Field>
                    </div>
                </div>
            </FormSection>
        </>
    );
}
