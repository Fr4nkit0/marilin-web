import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import HomeSaleModal from './HomeSaleModal';

const products = [{ id: 1, name: 'Bidón 20L', unitPrice: 2500, source: 'list' as const }];
const containerTypes = [{ id: 3, description: 'Bidón retornable' }];
const paymentMethods = [{ id: 5, name: 'Efectivo' }];

function modal(show: boolean, onClose: () => void = () => {}) {
    return (
        <HomeSaleModal
            show={show}
            customerId={7}
            customerName="Kiosco Ana"
            products={products}
            containerTypes={containerTypes}
            paymentMethods={paymentMethods}
            onClose={onClose}
        />
    );
}

describe('HomeSaleModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
        // The server accepts the sale at once.
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('opens empty for the next sale, never with the one just registered', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(true, onClose));

        fireEvent.change(screen.getByLabelText('Cantidad de Bidón 20L'), { target: { value: '3' } });
        fireEvent.change(screen.getByLabelText(/Medio de pago/), { target: { value: '5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Agregar' }));
        fireEvent.change(screen.getByLabelText('Cantidad de envases'), { target: { value: '2' } });
        fireEvent.change(screen.getByLabelText(/Notas/), { target: { value: 'Dejar en portería' } });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar venta' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.post).toHaveBeenCalledWith('/customers.home-sales.store/7', expect.anything(), expect.anything());

        rerender(modal(false));
        rerender(modal(true));

        await vi.waitFor(() => {
            expect((screen.getByLabelText('Cantidad de Bidón 20L') as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText(/Medio de pago/) as HTMLSelectElement).value).toBe('');
            expect(screen.queryByLabelText('Cantidad de envases')).toBeNull();
            expect((screen.getByLabelText(/Notas/) as HTMLTextAreaElement).value).toBe('');
        });
    });
});
