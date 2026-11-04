import { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';
import { Neighborhood } from '../types';

interface Props {
    neighborhood: Neighborhood | null;
    onClose: () => void;
}

export default function NeighborhoodStatusModal({ neighborhood, onClose }: Props) {
    const [processing, setProcessing] = useState(false);
    const disabling = neighborhood?.enabled ?? true;

    const confirm = () => {
        if (neighborhood === null) return;
        router.patch(
            route('neighborhoods.status', neighborhood.id),
            { enabled: !neighborhood.enabled },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
            },
        );
    };

    return (
        <Modal show={neighborhood !== null} onClose={onClose} maxWidth="md">
            <div className="px-6 pt-6 pb-4">
                <ModalTitle className="text-lg font-semibold text-ink">
                    {disabling ? '¿Deshabilitar el barrio?' : '¿Habilitar el barrio?'}
                </ModalTitle>
                <p className="text-sm text-ink-muted mt-2">
                    {disabling
                        ? `La app deja de ofrecer "${neighborhood?.name}" para las direcciones nuevas. Las direcciones que ya lo tienen no cambian.`
                        : `La app vuelve a ofrecer "${neighborhood?.name}" en la próxima sincronización.`}
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
