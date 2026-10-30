import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { FieldError } from '@/ui/Field';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import { DriverAccount } from '@/modules/Accounts/types';

interface Props {
    show: boolean;
    /** The account being edited; null creates a new one. */
    account: DriverAccount | null;
    onClose: () => void;
}

type AccountForm = {
    name: string;
    email: string;
    username: string;
    phone_number: string;
    password: string;
    password_confirmation: string;
};

const inputClass =
    'w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600';

export default function DriverAccountFormModal({ show, account, onClose }: Props) {
    const isNew = account === null;
    const { data, setData, post, patch, processing, errors, reset, clearErrors, transform } = useForm<AccountForm>({
        name: '',
        email: '',
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
            email: account?.email ?? '',
            username: account?.username ?? '',
            phone_number: account?.phone_number ?? '',
            password: '',
            password_confirmation: '',
        });
    }, [show, account]);

    // Empty optional fields are stored as null; an edit never sends a password.
    transform((form) => ({
        name: form.name,
        email: form.email,
        username: form.username || null,
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

    const field = (key: keyof AccountForm, label: string, type = 'text', optional = false) => (
        <label className="block">
            <span className="block text-sm font-medium text-ink mb-1">
                {label}
                {optional && <span className="text-ink-subtle font-normal"> (opcional)</span>}
            </span>
            <input
                type={type}
                value={data[key]}
                onChange={(e) => setData(key, e.target.value)}
                className={inputClass}
                autoComplete={type === 'password' ? 'new-password' : 'off'}
            />
            <FieldError message={errors[key]} className="mt-1" />
        </label>
    );

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} noValidate>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <h2 className="text-lg font-semibold text-ink">
                            {isNew ? 'Nuevo repartidor' : 'Editar repartidor'}
                        </h2>
                        <p className="text-sm text-ink-muted mt-1">
                            {isNew
                                ? 'Con este email y contraseña el repartidor entra a la app.'
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
                    {field('name', 'Nombre')}
                    {field('email', 'Email', 'email')}
                    {field('username', 'Usuario', 'text', true)}
                    {field('phone_number', 'Teléfono', 'tel', true)}
                    {isNew && field('password', 'Contraseña', 'password')}
                    {isNew && field('password_confirmation', 'Repetir contraseña', 'password')}
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
                        disabled={processing}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400"
                    >
                        {isNew ? 'Crear repartidor' : 'Guardar cambios'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
