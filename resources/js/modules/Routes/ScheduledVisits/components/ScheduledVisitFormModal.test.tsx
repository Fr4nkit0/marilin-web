import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ScheduledVisitFormModal from './ScheduledVisitFormModal';

function modal(show: boolean, onClose: () => void = () => {}) {
    return <ScheduledVisitFormModal show={show} customerId={7} customerName="Kiosco Ana" today="2026-11-03" onClose={onClose} />;
}

describe('ScheduledVisitFormModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
        // The server accepts the visit at once.
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('opens on today with no time or note for the next visit, never with the one just scheduled', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(true, onClose));

        fireEvent.change(screen.getByLabelText('Fecha'), { target: { value: '2026-11-10' } });
        fireEvent.change(screen.getByLabelText('Hora (opcional)'), { target: { value: '10:30' } });
        fireEvent.change(screen.getByLabelText('Nota (opcional)'), { target: { value: 'Quiere 2 bidones' } });
        fireEvent.click(screen.getByRole('button', { name: 'Agendar' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.post).toHaveBeenCalledWith(
            '/scheduled-visits.store/7',
            { date: '2026-11-10', scheduled_time: '10:30', note: 'Quiere 2 bidones' },
            expect.anything(),
        );

        rerender(modal(false));
        rerender(modal(true));

        await vi.waitFor(() => {
            expect((screen.getByLabelText('Fecha') as HTMLInputElement).value).toBe('2026-11-03');
            expect((screen.getByLabelText('Hora (opcional)') as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText('Nota (opcional)') as HTMLTextAreaElement).value).toBe('');
        });
    });
});
