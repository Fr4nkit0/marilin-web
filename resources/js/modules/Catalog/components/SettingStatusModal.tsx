import { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal, { ModalTitle } from '@/ui/Modal';
import Button from '@/ui/Button';

interface Props {
    show: boolean;
    /** Whether the row is enabled now: confirming disables it, otherwise it enables it. */
    enabled: boolean;
    title: string;
    description: string;
    /** The row's status endpoint. */
    url: string;
    onClose: () => void;
}

/** Confirms disabling or enabling a settings catalog row; nothing is ever deleted. */
export default function SettingStatusModal({ show, enabled, title, description, url, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        router.patch(
            url,
            { enabled: !enabled },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: onClose,
            },
        );
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="px-6 pt-6 pb-4">
                <ModalTitle className="text-lg font-semibold text-ink">{title}</ModalTitle>
                <p className="text-sm text-ink-muted mt-2">{description}</p>
            </div>
            <div className="flex justify-end gap-2 px-6 py-4 border-t border-hairline bg-canvas-soft/50">
                <Button variant="secondary" onClick={onClose}>
                    Cancelar
                </Button>
                <Button variant={enabled ? 'danger' : 'primary'} onClick={confirm} processing={processing}>
                    {enabled ? 'Deshabilitar' : 'Habilitar'}
                </Button>
            </div>
        </Modal>
    );
}
