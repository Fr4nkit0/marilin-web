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

/** A column header's sort button (the phone sort bar has a chip with the same name). */
function header(name: string): HTMLElement {
    return within(screen.getByRole('columnheader', { name })).getByRole('button');
}

describe('CashCloseDetailModal', () => {
    it('sorts a detail table by amount and keeps the total of every row', () => {
        render(<CashCloseDetailModal kind="credit" details={details} onClose={() => {}} />);

        expect(customers()).toEqual(['Hotel', 'Almacén', 'Kiosco']);

        fireEvent.click(header('Monto'));
        expect(customers()).toEqual(['Kiosco', 'Hotel', 'Almacén']);

        fireEvent.click(header('Monto'));
        expect(customers()).toEqual(['Almacén', 'Hotel', 'Kiosco']);
        expect(screen.getByText('Total (3 ventas)')).toBeTruthy();
        expect(screen.getByText('$1.250')).toBeTruthy();
    });

    it('sorts from the phone sort bar with the same columns as the headers', () => {
        render(<CashCloseDetailModal kind="credit" details={details} onClose={() => {}} />);

        const bar = screen.getByRole('group', { name: 'Ordenar por' });
        expect(within(bar).getAllByRole('button').map((chip) => chip.textContent)).toEqual(['Hora', 'Cliente', 'Venta', 'Origen', 'Monto']);

        fireEvent.click(within(bar).getByRole('button', { name: 'Monto' }));
        expect(customers()).toEqual(['Kiosco', 'Hotel', 'Almacén']);
        expect(within(bar).getByRole('button', { name: 'Monto, ascendente' }).getAttribute('aria-pressed')).toBe('true');
        expect(header('Monto').closest('th')?.getAttribute('aria-sort')).toBe('ascending');
    });

    it('labels each card line and the total, with the customer as the card title', () => {
        render(<CashCloseDetailModal kind="credit" details={details} onClose={() => {}} />);

        const [hour, customer, sale, origin, amount] = within(screen.getAllByRole('rowgroup')[1]).getAllByRole('row')[0].querySelectorAll('td');
        expect([hour, sale, origin, amount].map((cell) => cell.dataset.label)).toEqual(['Hora', 'Venta', 'Origen', 'Monto']);
        expect(customer.hasAttribute('data-primary')).toBe(true);

        const total = within(screen.getAllByRole('rowgroup')[2]).getByText('$1.250');
        expect(total.dataset.label).toBe('Total (3 ventas)');
    });
});
