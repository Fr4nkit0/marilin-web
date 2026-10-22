import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { DriverAccount } from '@/types/user-account';

interface Props {
    account: DriverAccount | null;
    onClose: () => void;
}

export default function DriverStatusModal({ account, onClose }: Props) {
    const [processing, setProcessing] = useState(false);
    const disabling = account?.enabled ?? true;

    const confirm = () => {
        if (account === null) return;
        router.patch(
            route('users.status', account.id),
            { enabled: !account.enabled },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
            },
        );
    };

    return (
        <Modal show={account !== null} onClose={onClose} maxWidth="md">
            <div className="px-6 pt-6 pb-4">
                <h2 className="text-lg font-semibold text-slate-900">
                    {disabling ? '¿Deshabilitar la cuenta?' : '¿Habilitar la cuenta?'}
                </h2>
                <p className="text-sm text-slate-500 mt-2">
                    {disabling
                        ? `${account?.name} no va a poder entrar a la app ni al panel, y se cierran sus sesiones abiertas. Sus ventas, visitas y cobros se conservan.`
                        : `${account?.name} va a poder volver a entrar con su contraseña.`}
                </p>
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
                    type="button"
                    onClick={confirm}
                    disabled={processing}
                    className={`px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer ${
                        disabling ? 'bg-red-600 hover:bg-red-500' : 'bg-blue-600 hover:bg-blue-500'
                    }`}
                >
                    {disabling ? 'Deshabilitar' : 'Habilitar'}
                </button>
            </div>
        </Modal>
    );
}
