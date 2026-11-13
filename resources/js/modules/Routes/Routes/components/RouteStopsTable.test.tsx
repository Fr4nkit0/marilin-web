import { fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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
    // jsdom has no scrolling; the pager scrolls the table back to its top.
    const scrollIntoView = vi.fn();
    beforeEach(() => {
        scrollIntoView.mockClear();
        Element.prototype.scrollIntoView = scrollIntoView;
    });

    it('sorts the stops by customer both ways, each stop keeping its number in the route', () => {
        render(<RouteStopsTable stops={[stop(1, 'Hotel'), stop(2, 'Almacén'), stop(3, 'Kiosco')]} />);

        expect(rows()).toEqual(['1 Hotel', '2 Almacén', '3 Kiosco']);

        const table = within(screen.getByRole('table'));
        fireEvent.click(table.getByRole('button', { name: 'Cliente' }));
        expect(rows()).toEqual(['2 Almacén', '1 Hotel', '3 Kiosco']);

        fireEvent.click(table.getByRole('button', { name: 'Cliente' }));
        expect(rows()).toEqual(['3 Kiosco', '1 Hotel', '2 Almacén']);
        expect(screen.getByRole('columnheader', { name: 'Cliente' }).getAttribute('aria-sort')).toBe('descending');
    });

    it('sorts the stops from the phone sort bar like the headers do', () => {
        render(<RouteStopsTable stops={[stop(1, 'Hotel'), stop(2, 'Almacén'), stop(3, 'Kiosco')]} />);
        const bar = within(screen.getByRole('group', { name: 'Ordenar por' }));

        fireEvent.click(bar.getByRole('button', { name: 'Cliente' }));
        expect(rows()).toEqual(['2 Almacén', '1 Hotel', '3 Kiosco']);
        expect(bar.getByRole('button', { name: 'Cliente, ascendente' }).getAttribute('aria-pressed')).toBe('true');

        fireEvent.click(bar.getByRole('button', { name: 'Cliente, ascendente' }));
        expect(rows()).toEqual(['3 Kiosco', '1 Hotel', '2 Almacén']);
        expect(screen.getByRole('columnheader', { name: 'Cliente' }).getAttribute('aria-sort')).toBe('descending');
    });

    it('labels each stop cell for its phone card', () => {
        render(<RouteStopsTable stops={[stop(1, 'Hotel')]} />);
        const [, body] = screen.getAllByRole('rowgroup');
        const cells = within(body).getAllByRole('cell');

        expect(cells.map((cell) => cell.getAttribute('data-label'))).toEqual([
            'Parada',
            null,
            'Dirección',
            'Estado',
            'Hora',
            'Resultado',
        ]);
        expect(cells[1].hasAttribute('data-primary')).toBe(true);
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

    it('pages the stops fifteen at a time, each keeping its number in the route', () => {
        const many = Array.from({ length: 20 }, (_, index) => stop(index + 1, `Cliente ${index + 1}`));
        render(<RouteStopsTable stops={many} />);

        expect(rows()).toHaveLength(15);
        expect(rows()[0]).toBe('1 Cliente 1');
        expect(screen.getByText('Página 1 de 2 (20 paradas)')).toBeTruthy();
        expect(screen.getByText('Hoja de Ruta y Paradas (20)', { exact: false })).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Siguiente »' }));
        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'start' });
        expect(rows()).toEqual(['16 Cliente 16', '17 Cliente 17', '18 Cliente 18', '19 Cliente 19', '20 Cliente 20']);
        expect(screen.getByText('Página 2 de 2 (20 paradas)')).toBeTruthy();
    });

    it('selects every pending stop of the route, not only the ones on the page', () => {
        const many = Array.from({ length: 20 }, (_, index) => stop(index + 1, `Cliente ${index + 1}`));
        const onSelect = vi.fn();
        render(<RouteStopsTable stops={many} selected={[]} onSelect={onSelect} />);

        fireEvent.click(screen.getByRole('checkbox', { name: 'Seleccionar todas las paradas pendientes' }));
        expect(onSelect).toHaveBeenLastCalledWith(many.map((each) => each.id));
    });

    it('shows no pager when the stops fit in one page', () => {
        render(<RouteStopsTable stops={[stop(1, 'Hotel')]} />);

        expect(screen.queryByText(/Página/)).toBeNull();
    });

    it('marks a covered stop with the driver it was moved from', () => {
        render(<RouteStopsTable stops={[{ ...stop(1, 'Hotel'), movedFrom: 'Ana Ausente' }, stop(2, 'Almacén')]} />);

        expect(screen.getByText('Movida de Ana Ausente')).toBeTruthy();
        expect(screen.getAllByText(/Movida de/)).toHaveLength(1);
    });
});
