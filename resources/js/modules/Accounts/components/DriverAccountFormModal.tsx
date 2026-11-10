import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import Button from '@/ui/Button';
import { Field, Input } from '@/ui/Field';
import { formatPhone } from '@/lib/format';
import { Truck, X } from 'lucide-react';
import Modal, { ModalTitle } from '@/ui/Modal';
import { DriverAccount } from '@/modules/Accounts/types';

interface Props {
    show: boolean;
    /** The account being edited; null creates a new one. */
    account: DriverAccount | null;
    onClose: () => void;
}

type AccountForm = {
    name: string;
    username: string;
    phone_number: string;
    password: string;
    password_confirmation: string;
};

export default function DriverAccountFormModal({ show, account, onClose }: Props) {
    const isNew = account === null;
    const { data, setData, post, patch, processing, errors, reset, clearErrors, transform } = useForm<AccountForm>({
        name: '',
        username: '',
        phone_number: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        if (!show) return;
        clearErrors();
        setData({
            name: account?.name ?? '',
            username: account?.username ?? '',
            phone_number: account?.phone_number ?? '',
            password: '',
            password_confirmation: '',
        });
    }, [show, account]);

    // Drivers have no email: they sign in to the app with the username. Empty optional fields are stored as null; an edit never sends a password.
    transform((form) => ({
        name: form.name,
        username: form.username,
        phone_number: form.phone_number || null,
        ...(isNew ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
    }));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };
        if (isNew) {
            post(route('users.store'), options);
        } else {
            patch(route('users.update', account.id), options);
        }
    };

    const field = (key: keyof AccountForm, label: string, options: { type?: string; optional?: boolean; hint?: string } = {}) => {
        const { type = 'text', optional = false, hint } = options;

        return (
            <Field
                label={
                    <>
                        {label}
                        {optional && <span className="text-ink-subtle font-normal"> (opcional)</span>}
                    </>
                }
                error={errors[key]}
                hint={hint}
            >
                <Input
                    type={type}
                    value={data[key]}
                    invalid={Boolean(errors[key])}
                    onChange={(e) => setData(key, key === 'phone_number' ? formatPhone(e.target.value) : e.target.value)}
                    autoComplete={type === 'password' ? 'new-password' : 'off'}
                    autoCapitalize={key === 'username' ? 'none' : undefined}
                />
            </Field>
        );
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <ModalTitle className="text-lg font-semibold text-ink">
                            {isNew ? 'Nuevo repartidor' : 'Editar repartidor'}
                        </ModalTitle>
                        <p className="text-sm text-ink-muted mt-1">
                            {isNew
                                ? 'Con este usuario y contraseña el repartidor entra a la app.'
                                : 'Los cambios valen para el próximo ingreso a la app.'}
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

                <div className="px-6 py-5 space-y-4">
                    {/* The only profile this screen creates: admin accounts are not managed here. */}
                    <div className="flex items-center gap-3 rounded-lg bg-surface-1 px-3 py-2.5">
                        <Truck className="w-4 h-4 shrink-0 text-ink-muted" />
                        <p className="text-sm text-ink-secondary">
                            Perfil <span className="font-semibold text-ink">Repartidor</span>: entra solo a la app, no al panel web.
                        </p>
                    </div>
                    {field('name', 'Nombre')}
                    {field('username', 'Usuario', { hint: 'Con este usuario entra a la app.' })}
                    {field('phone_number', 'Teléfono', { type: 'tel', optional: true })}
                    {isNew && field('password', 'Contraseña', { type: 'password', hint: 'Al menos 8 caracteres.' })}
                    {isNew && field('password_confirmation', 'Repetir contraseña', { type: 'password' })}
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" processing={processing}>
                        {isNew ? 'Crear repartidor' : 'Guardar cambios'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
