import React, { FormEvent, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { X } from 'lucide-react';
import Modal from '@/ui/Modal';
import { DriverAccount } from '@/types/user-account';

interface Props {
    account: DriverAccount | null;
    onClose: () => void;
}

const inputClass =
    'w-full py-2 px-3 text-sm border border-gray-200/60 bg-white text-slate-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-600/20 focus:border-slate-900';

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
                <div className="flex items-start justify-between gap-4 px-6 pt-6 pb-4 border-b border-gray-100">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">Cambiar contraseña</h2>
                        <p className="text-sm text-slate-500 mt-1">
                            {account?.name}: se cierra su sesión en la app y tiene que entrar con la contraseña nueva.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Cerrar"
                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="px-6 py-5 space-y-4">
                    <label className="block">
                        <span className="block text-sm font-medium text-slate-900 mb-1">Contraseña nueva</span>
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={inputClass}
                            autoComplete="new-password"
                        />
                        {errors.password && <span className="block text-sm text-red-600 mt-1">{errors.password}</span>}
                    </label>
                    <label className="block">
                        <span className="block text-sm font-medium text-slate-900 mb-1">Repetir contraseña</span>
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className={inputClass}
                            autoComplete="new-password"
                        />
                    </label>
                </div>

                <div className="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-slate-50/50">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-slate-700 bg-white ring-1 ring-inset ring-slate-200/80 hover:bg-slate-50 cursor-pointer"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-4 py-2 rounded-lg text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    >
                        Cambiar contraseña
                    </button>
                </div>
            </form>
        </Modal>
    );
}
