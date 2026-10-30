import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import { DriverAccount } from '@/modules/Accounts/types';

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
                <ModalTitle className="text-lg font-semibold text-ink">
                    {disabling ? '¿Deshabilitar la cuenta?' : '¿Habilitar la cuenta?'}
                </ModalTitle>
                <p className="text-sm text-ink-muted mt-2">
                    {disabling
                        ? `${account?.name} no va a poder entrar a la app ni al panel, y se cierran sus sesiones abiertas. Sus ventas, visitas y cobros se conservan.`
                        : `${account?.name} va a poder volver a entrar con su contraseña.`}
                </p>
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
                    type="button"
                    onClick={confirm}
                    disabled={processing}
                    className={`px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer ${
                        disabling ? 'bg-red-600 hover:bg-red-500' : 'bg-sky-600 hover:bg-sky-700 dark:bg-gradient-to-r dark:from-sky-500 dark:to-cyan-500 dark:hover:from-sky-400 dark:hover:to-cyan-400'
                    }`}
                >
                    {disabling ? 'Deshabilitar' : 'Habilitar'}
                </button>
            </div>
        </Modal>
    );
}
