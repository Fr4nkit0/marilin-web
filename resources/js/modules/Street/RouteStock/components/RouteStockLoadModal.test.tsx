import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import RouteStockLoadModal from './RouteStockLoadModal';

const products = [
    { id: 1, name: 'Bidón 20L' },
    { id: 2, name: 'Sifón 1.5L' },
];

function modal(routeId: number | null, onClose: () => void = () => {}) {
    return <RouteStockLoadModal routeId={routeId} driverName="Lucía Fernández" isFirstLoad={false} products={products} onClose={onClose} />;
}

describe('RouteStockLoadModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
        // The server accepts the load at once.
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('opens empty for the next load, never with the quantities just registered', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(9, onClose));

        fireEvent.change(screen.getByLabelText('Unidades de Bidón 20L'), { target: { value: '25' } });
        fireEvent.change(screen.getByLabelText('Unidades de Sifón 1.5L'), { target: { value: '4' } });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar carga' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.post).toHaveBeenCalledWith(
            '/routes.loads.store/9',
            { items: [{ product_id: 1, quantity: '25' }, { product_id: 2, quantity: '4' }] },
            expect.anything(),
        );

        rerender(modal(null));
        rerender(modal(9));

        await vi.waitFor(() => {
            expect((screen.getByLabelText('Unidades de Bidón 20L') as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText('Unidades de Sifón 1.5L') as HTMLInputElement).value).toBe('');
        });
    });
});
