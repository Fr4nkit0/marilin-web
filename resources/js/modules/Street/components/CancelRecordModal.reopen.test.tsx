import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CancelRecordModal, { type CancelTarget } from './CancelRecordModal';

const sale: CancelTarget = { url: '/sales/12/cancel', title: 'Anular venta #12', summary: 'Kiosco Ana · $1.000 · 20/10/2026' };
const payment: CancelTarget = { url: '/payments/30/cancel', title: 'Anular cobro #30', summary: 'Kiosco Ana · $600 · 20/10/2026' };

function modal(target: CancelTarget | null, onClose: () => void = () => {}) {
    return <CancelRecordModal target={target} consequence="Se devuelve el total." onClose={onClose} />;
}

describe('CancelRecordModal', () => {
    beforeEach(() => {
        // The server accepts the cancellation at once.
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('asks for a new reason on the next cancellation, never with the one just given', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(sale, onClose));

        fireEvent.change(screen.getByLabelText('Motivo'), { target: { value: 'Cargada por error' } });
        fireEvent.click(screen.getByRole('button', { name: 'Anular' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.post).toHaveBeenCalledWith('/sales/12/cancel', { reason: 'Cargada por error' }, expect.anything());

        rerender(modal(null));
        rerender(modal(payment));

        await vi.waitFor(() => expect((screen.getByLabelText('Motivo') as HTMLTextAreaElement).value).toBe(''));
    });
});
