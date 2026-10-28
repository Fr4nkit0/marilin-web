import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import { DriverAccount } from '@/modules/Accounts/types';

interface Props {
    account: DriverAccount | null;
    onClose: () => void;
}

const inputClass =
    'w-full py-2 px-3 text-sm border border-hairline/60 bg-canvas text-ink rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600';

export default function DriverPasswordModal({ account, onClose }: Props) {
    const { data, setData, patch, processing, errors, reset, clearErrors } = useForm({
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        reset();
        clearErrors();
    }, [account]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (account === null) return;
        patch(route('users.password', account.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal show={account !== null} onClose={onClose} maxWidth="md">
            <form onSubmit={submit}>
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-hairline">
                    <div>
                        <h2 className="text-lg font-semibold text-ink">Cambiar contraseña</h2>
                        <p className="text-sm text-ink-muted mt-1">
                            {account?.name}: se cierra su sesión en la app y tiene que entrar con la contraseña nueva.
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
                    <label className="block">
                        <span className="block text-sm font-medium text-ink mb-1">Contraseña nueva</span>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={inputClass}
                            autoComplete="new-password"
                        />
                        {errors.password && <span className="block text-sm text-red-600 dark:text-red-300 mt-1">{errors.password}</span>}
                    </label>
                    <label className="block">
                        <span className="block text-sm font-medium text-ink mb-1">Repetir contraseña</span>
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className={inputClass}
                            autoComplete="new-password"
                        />
                    </label>
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
                        Cambiar contraseña
                    </button>
                </div>
            </form>
        </Modal>
    );
}
