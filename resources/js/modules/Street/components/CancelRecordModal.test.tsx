import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CancelRecordModal, { type CancelTarget } from './CancelRecordModal';

vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        data: { reason: '' },
        setData: () => {},
        post: () => {},
        processing: false,
        errors: {},
        reset: () => {},
        clearErrors: () => {},
    }),
}));

const sale: CancelTarget = { url: '/sales/12/cancel', title: 'Anular venta #12', summary: 'Kiosco Ana · $1.000 · 20/10/2026' };

describe('CancelRecordModal', () => {
    it('warns that the payments still in force of a sale stay active, listing each one', () => {
        render(
            <CancelRecordModal
                target={{
                    ...sale,
                    linkedPayments: [
                        { id: 1, amount: '600.00', method: 'Efectivo' },
                        { id: 2, amount: '400.00', method: 'Transferencia Bancaria' },
                    ],
                }}
                consequence="Se devuelve el total a la cuenta corriente del cliente."
                onClose={() => {}}
            />,
        );

        const warning = screen.getByRole('alert');
        expect(warning.textContent).toContain('Esta venta tiene 2 cobros que siguen vigentes:');
        expect(screen.getByText('$600 · Efectivo')).toBeTruthy();
        expect(screen.getByText('$400 · Transferencia')).toBeTruthy();
        expect(warning.textContent).toContain('el cliente queda con saldo a favor hasta que los anules en Cobros.');
    });

    it('says nothing about payments when the sale has none in force', () => {
        render(<CancelRecordModal target={{ ...sale, linkedPayments: [] }} consequence="Se devuelve el total." onClose={() => {}} />);

        expect(screen.getByRole('dialog', { name: 'Anular venta #12' })).toBeTruthy();
        expect(screen.queryByRole('alert')).toBeNull();
    });
});
