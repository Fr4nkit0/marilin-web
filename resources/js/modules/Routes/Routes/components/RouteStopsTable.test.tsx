import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import RouteStopsTable, { type RouteStop } from './RouteStopsTable';

function stop(id: number, name: string): RouteStop {
    return {
        id,
        plannedSequence: id,
        customer: { id, name, address: `Calle ${id}` },
        status: 'PENDING',
        done: false,
        is_requested: false,
        note: null,
    };
}

/** Each row's stop number and customer, top to bottom. */
function rows(): string[] {
    const [, body] = screen.getAllByRole('rowgroup');

    return within(body)
        .getAllByRole('row')
        .map((row) => {
            const cells = within(row).getAllByRole('cell');
            return `${cells[0].textContent} ${cells[1].textContent}`;
        });
}

describe('RouteStopsTable', () => {
    it('sorts the stops by customer both ways, each stop keeping its number in the route', () => {
        render(<RouteStopsTable stops={[stop(1, 'Hotel'), stop(2, 'Almacén'), stop(3, 'Kiosco')]} />);

        expect(rows()).toEqual(['1 Hotel', '2 Almacén', '3 Kiosco']);

        fireEvent.click(screen.getByRole('button', { name: 'Cliente' }));
        expect(rows()).toEqual(['2 Almacén', '1 Hotel', '3 Kiosco']);

        fireEvent.click(screen.getByRole('button', { name: 'Cliente' }));
        expect(rows()).toEqual(['3 Kiosco', '1 Hotel', '2 Almacén']);
        expect(screen.getByRole('columnheader', { name: 'Cliente' }).getAttribute('aria-sort')).toBe('descending');
    });
});
