import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import Button from '@/ui/Button';
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
                        ? `${account?.name} no va a poder entrar a la app, y se cierran sus sesiones abiertas. Sus ventas, visitas y cobros se conservan.`
                        : `${account?.name} va a poder volver a entrar con su contraseña.`}
                </p>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                <Button variant="secondary" onClick={onClose}>
                    Cancelar
                </Button>
                <Button variant={disabling ? 'danger' : 'primary'} onClick={confirm} processing={processing}>
                    {disabling ? 'Deshabilitar' : 'Habilitar'}
                </Button>
            </div>
        </Modal>
    );
}
