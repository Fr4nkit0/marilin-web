import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
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
        movedFrom: null,
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

    it('selects the stops still to be made, one by one or all at once', () => {
        const done = { ...stop(3, 'Kiosco'), status: 'FINISHED' as const, done: true };
        const onSelect = vi.fn();
        const { rerender } = render(
            <RouteStopsTable stops={[stop(1, 'Hotel'), stop(2, 'Almacén'), done]} selected={[]} onSelect={onSelect} />,
        );

        expect(screen.queryByRole('checkbox', { name: 'Seleccionar Kiosco' })).toBeNull();

        fireEvent.click(screen.getByRole('checkbox', { name: 'Seleccionar Almacén' }));
        expect(onSelect).toHaveBeenLastCalledWith([2]);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Seleccionar todas las paradas pendientes' }));
        expect(onSelect).toHaveBeenLastCalledWith([1, 2]);

        rerender(<RouteStopsTable stops={[stop(1, 'Hotel'), stop(2, 'Almacén'), done]} selected={[1, 2]} onSelect={onSelect} />);
        expect((screen.getByRole('checkbox', { name: 'Seleccionar Hotel' }) as HTMLInputElement).checked).toBe(true);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Seleccionar Hotel' }));
        expect(onSelect).toHaveBeenLastCalledWith([2]);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Seleccionar todas las paradas pendientes' }));
        expect(onSelect).toHaveBeenLastCalledWith([]);
    });

    it('shows no checkboxes when the stops cannot be moved', () => {
        render(<RouteStopsTable stops={[stop(1, 'Hotel')]} />);

        expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
    });

    it('marks a covered stop with the driver it was moved from', () => {
        render(<RouteStopsTable stops={[{ ...stop(1, 'Hotel'), movedFrom: 'Ana Ausente' }, stop(2, 'Almacén')]} />);

        expect(screen.getByText('Movida de Ana Ausente')).toBeTruthy();
        expect(screen.getAllByText(/Movida de/)).toHaveLength(1);
    });
});
