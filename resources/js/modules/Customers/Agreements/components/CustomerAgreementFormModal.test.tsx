import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CustomerAgreementFormModal from './CustomerAgreementFormModal';

const products = [{ id: 1, name: 'Bidón 20L', price: 2500 }];

function modal(show: boolean, onClose: () => void = () => {}) {
    return (
        <CustomerAgreementFormModal
            show={show}
            customerId={7}
            customerName="Kiosco Ana"
            products={products}
            today="2026-11-03"
            onClose={onClose}
        />
    );
}

describe('CustomerAgreementFormModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
        // The server accepts the agreement at once.
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('opens blank from today for the next agreement, never with the one just created', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(true, onClose));

        fireEvent.change(screen.getByLabelText(/^Producto/), { target: { value: '1' } });
        fireEvent.change(screen.getByLabelText(/^Precio acordado/), { target: { value: '2100' } });
        fireEvent.change(screen.getByLabelText(/^Desde/), { target: { value: '2026-11-05' } });
        fireEvent.change(screen.getByLabelText(/^Hasta/), { target: { value: '2026-12-31' } });
        fireEvent.change(screen.getByLabelText(/^Motivo/), { target: { value: 'Cliente mayorista' } });
        fireEvent.click(screen.getByRole('button', { name: 'Crear acuerdo' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.post).toHaveBeenCalledWith(
            '/customers.agreements.store/7',
            { product_id: '1', unit_price: '2100', valid_from: '2026-11-05', valid_to: '2026-12-31', reason: 'Cliente mayorista' },
            expect.anything(),
        );

        rerender(modal(false));
        rerender(modal(true));

        await vi.waitFor(() => {
            expect((screen.getByLabelText(/^Producto/) as HTMLSelectElement).value).toBe('');
            expect((screen.getByLabelText(/^Precio acordado/) as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText(/^Desde/) as HTMLInputElement).value).toBe('2026-11-03');
            expect((screen.getByLabelText(/^Hasta/) as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText(/^Motivo/) as HTMLTextAreaElement).value).toBe('');
        });
    });
});
