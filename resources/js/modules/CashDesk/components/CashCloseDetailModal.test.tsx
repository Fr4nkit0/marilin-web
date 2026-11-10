import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { CashCloseDetails } from '../types';
import CashCloseDetailModal from './CashCloseDetailModal';

const details: CashCloseDetails = {
    cash: [],
    transfer: [],
    credit: [
        { id: 21, customer: 'Hotel', at: '2026-11-10 09:00:00', amount: 300, origin: 'stop' },
        { id: 22, customer: 'Almacén', at: '2026-11-10 11:00:00', amount: 900, origin: 'loose' },
        { id: 23, customer: 'Kiosco', at: '2026-11-10 10:00:00', amount: 50, origin: 'stop' },
    ],
    postClose: [],
    containers: [],
};

/** The customer of each body row, top to bottom. */
function customers(): string[] {
    const body = screen.getAllByRole('rowgroup')[1];

    return within(body)
        .getAllByRole('row')
        .map((row) => within(row).getAllByRole('cell')[1].textContent ?? '');
}

describe('CashCloseDetailModal', () => {
    it('sorts a detail table by amount and keeps the total of every row', () => {
        render(<CashCloseDetailModal kind="credit" details={details} onClose={() => {}} />);

        expect(customers()).toEqual(['Hotel', 'Almacén', 'Kiosco']);

        fireEvent.click(screen.getByRole('button', { name: 'Monto' }));
        expect(customers()).toEqual(['Kiosco', 'Hotel', 'Almacén']);

        fireEvent.click(screen.getByRole('button', { name: 'Monto' }));
        expect(customers()).toEqual(['Almacén', 'Hotel', 'Kiosco']);
        expect(screen.getByText('Total (3 ventas)')).toBeTruthy();
        expect(screen.getByText('$1.250')).toBeTruthy();
    });
});
