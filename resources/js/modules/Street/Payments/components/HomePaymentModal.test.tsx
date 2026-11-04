import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import HomePaymentModal from './HomePaymentModal';

const paymentMethods = [{ id: 5, name: 'Efectivo' }];

function modal(show: boolean, onClose: () => void = () => {}) {
    return (
        <HomePaymentModal
            show={show}
            customerId={7}
            customerName="Kiosco Ana"
            paymentMethods={paymentMethods}
            balance={5000}
            onClose={onClose}
        />
    );
}

describe('HomePaymentModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
        // The server accepts the payment at once.
        vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('opens empty for the next payment, never with the one just registered', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(true, onClose));

        fireEvent.change(screen.getByLabelText(/Monto/), { target: { value: '1000' } });
        fireEvent.change(screen.getByLabelText(/Método de pago/), { target: { value: '5' } });
        fireEvent.click(screen.getByRole('button', { name: 'Registrar cobro' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.post).toHaveBeenCalledWith(
            '/customers.home-payments.store/7',
            { amount: '1000', payment_method_id: '5' },
            expect.anything(),
        );

        rerender(modal(false));
        rerender(modal(true));

        await vi.waitFor(() => {
            expect((screen.getByLabelText(/Monto/) as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText(/Método de pago/) as HTMLSelectElement).value).toBe('');
        });
    });
});
