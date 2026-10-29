import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
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
});
