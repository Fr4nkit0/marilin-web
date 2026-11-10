import { act, renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { listSort, nextSort, sortRows, useSort } from './sort';

describe('nextSort', () => {
    it('starts a new column ascending and flips the same column', () => {
        expect(nextSort({ key: null, direction: 'asc' }, 'name')).toEqual({ key: 'name', direction: 'asc' });
        expect(nextSort({ key: 'name', direction: 'asc' }, 'name')).toEqual({ key: 'name', direction: 'desc' });
        expect(nextSort({ key: 'name', direction: 'desc' }, 'name')).toEqual({ key: 'name', direction: 'asc' });
        expect(nextSort({ key: 'name', direction: 'desc' }, 'total')).toEqual({ key: 'total', direction: 'asc' });
    });
});

describe('sortRows', () => {
    it('orders text the Spanish way, ignoring case and accents and reading numbers inside it', () => {
        const names = ['lucía', 'Álvarez', 'Ruta 10', 'Martín', 'Ruta 2'];

        expect(sortRows(names, (n) => n, 'asc')).toEqual(['Álvarez', 'lucía', 'Martín', 'Ruta 2', 'Ruta 10']);
        expect(sortRows(names, (n) => n, 'desc')).toEqual(['Ruta 10', 'Ruta 2', 'Martín', 'lucía', 'Álvarez']);
    });

    it('orders numbers by value and leaves missing values last in both directions', () => {
        const rows = [{ v: 10 }, { v: null }, { v: 2 }, { v: 33 }];

        expect(sortRows(rows, (r) => r.v, 'asc').map((r) => r.v)).toEqual([2, 10, 33, null]);
        expect(sortRows(rows, (r) => r.v, 'desc').map((r) => r.v)).toEqual([33, 10, 2, null]);
    });

    it('keeps the original order between equal values and does not touch the input', () => {
        const rows = [
            { id: 1, status: 'b' },
            { id: 2, status: 'a' },
            { id: 3, status: 'b' },
        ];

        expect(sortRows(rows, (r) => r.status, 'desc').map((r) => r.id)).toEqual([1, 3, 2]);
        expect(rows.map((r) => r.id)).toEqual([1, 2, 3]);
    });
});

describe('listSort', () => {
    it('asks the server for the next order of a column and reports the current one', () => {
        const set = vi.fn();
        const sort = listSort({ sort: 'name', direction: 'desc' }, set);

        expect(sort.directionOf('name')).toBe('desc');
        expect(sort.directionOf('balance')).toBeNull();

        sort.toggle('name');
        expect(set).toHaveBeenLastCalledWith({ sort: 'name', direction: 'asc' });

        sort.toggle('balance');
        expect(set).toHaveBeenLastCalledWith({ sort: 'balance', direction: 'asc' });
    });

    it('reports no sorted column while the list keeps its default order', () => {
        const sort = listSort({ sort: '', direction: '' }, vi.fn());

        expect(sort.directionOf('name')).toBeNull();
    });
});

describe('useSort', () => {
    it('keeps the server order until a header is clicked, then sorts and flips', () => {
        const rows = [{ name: 'Carlos' }, { name: 'Ana' }, { name: 'Beto' }];
        const { result } = renderHook(() => useSort(rows, { name: (r) => r.name }));

        expect(result.current.rows.map((r) => r.name)).toEqual(['Carlos', 'Ana', 'Beto']);
        expect(result.current.directionOf('name')).toBeNull();

        act(() => result.current.toggle('name'));
        expect(result.current.rows.map((r) => r.name)).toEqual(['Ana', 'Beto', 'Carlos']);
        expect(result.current.directionOf('name')).toBe('asc');

        act(() => result.current.toggle('name'));
        expect(result.current.rows.map((r) => r.name)).toEqual(['Carlos', 'Beto', 'Ana']);
        expect(result.current.directionOf('name')).toBe('desc');
    });
});
