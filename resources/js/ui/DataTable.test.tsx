import { render, screen, within } from '@testing-library/react';
import { fireEvent } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import DataTable from './DataTable';

type Row = { id: number; name: string; total: number };

const columns = [
    { header: 'Cliente', cell: (row: Row) => row.name },
    { header: 'Total', align: 'right' as const, cell: (row: Row) => `$${row.total}` },
];

describe('DataTable', () => {
    it('scrolls sideways with a cue when it does not fit', () => {
        const { container } = render(<DataTable columns={columns} rows={[]} rowKey={(row) => row.id} empty="Sin ventas." />);

        expect((container.firstChild as HTMLElement).className).toContain('table-scroll');
    });

    it('renders one header per column and one row per record', () => {
        render(<DataTable columns={columns} rows={[{ id: 1, name: 'Ana', total: 10 }, { id: 2, name: 'Juan', total: 20 }]} rowKey={(row) => row.id} empty="Sin ventas." />);

        expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual(['Cliente', 'Total']);
        const rows = screen.getAllByRole('row').slice(1);
        expect(rows).toHaveLength(2);
        expect(within(rows[1]).getByText('$20').className).toContain('text-right');
    });

    it('says why the list is empty', () => {
        render(<DataTable columns={columns} rows={[]} rowKey={(row) => row.id} empty="Sin ventas." />);

        const message = screen.getByText('Sin ventas.');
        expect(message.closest('td')?.getAttribute('colspan')).toBe('2');
        // The message stays inside the visible width when the empty table scrolls sideways.
        expect(message.className).toContain('table-empty');
    });

    describe('on a phone, as cards', () => {
        it('uses the card layout and labels every cell with its column', () => {
            const { container } = render(
                <DataTable
                    columns={[
                        ...columns,
                        { header: <span>Estado</span>, label: 'Estado', cell: () => 'Activo' },
                        { header: <span className="sr-only">Acciones</span>, cell: () => 'Ver' },
                    ]}
                    rows={[{ id: 1, name: 'Ana', total: 10 }]}
                    rowKey={(row) => row.id}
                    empty="Sin ventas."
                />,
            );

            expect(container.querySelector('table')?.className).toContain('table-cards');
            const cells = screen.getAllByRole('cell');
            expect(cells.map((cell) => cell.getAttribute('data-label'))).toEqual(['Cliente', 'Total', 'Estado', null]);
        });

        it('makes the primary column the title of each card', () => {
            render(
                <DataTable
                    columns={[{ ...columns[0], primary: true }, columns[1]]}
                    rows={[{ id: 1, name: 'Ana', total: 10 }]}
                    rowKey={(row) => row.id}
                    empty="Sin ventas."
                />,
            );

            const [title, total] = screen.getAllByRole('cell');
            expect(title.hasAttribute('data-primary')).toBe(true);
            expect(title.hasAttribute('data-label')).toBe(false);
            expect(total.hasAttribute('data-primary')).toBe(false);
        });

        it('offers the sortable columns in a sort bar, since the headers are hidden', () => {
            const onSortName = vi.fn();
            const onSortTotal = vi.fn();
            render(
                <DataTable
                    columns={[
                        { ...columns[0], sort: { direction: 'asc', onSort: onSortName } },
                        { ...columns[1], sort: { direction: null, onSort: onSortTotal } },
                    ]}
                    rows={[{ id: 1, name: 'Ana', total: 10 }]}
                    rowKey={(row) => row.id}
                    empty="Sin ventas."
                />,
            );

            const bar = screen.getByRole('group', { name: 'Ordenar por' });
            const [byName, byTotal] = within(bar).getAllByRole('button');
            expect(byName.textContent).toBe('Cliente');
            expect(byName.getAttribute('aria-pressed')).toBe('true');
            expect(byName.getAttribute('aria-label')).toBe('Cliente, ascendente');
            expect(byTotal.getAttribute('aria-pressed')).toBe('false');

            fireEvent.click(byTotal);
            expect(onSortTotal).toHaveBeenCalledTimes(1);
            expect(onSortName).not.toHaveBeenCalled();
        });

        it('shows no sort bar when nothing sorts', () => {
            render(<DataTable columns={columns} rows={[]} rowKey={(row) => row.id} empty="Sin ventas." />);

            expect(screen.queryByRole('group', { name: 'Ordenar por' })).toBeNull();
        });
    });
});
