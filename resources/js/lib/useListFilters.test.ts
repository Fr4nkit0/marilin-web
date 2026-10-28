import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useListFilters } from './useListFilters';

const get = vi.fn();

vi.mock('@inertiajs/react', () => ({ router: { get: (...args: unknown[]) => get(...args) } }));

beforeEach(() => {
    get.mockClear();
    vi.stubGlobal('route', (name: string) => `/${name}`);
});

const initial = { search: 'ana', date: '2026-10-20', sale_type: 'all' };
const defaults = { search: '', date: '', sale_type: 'all' };

describe('useListFilters', () => {
    it('starts from the filters the server echoed back', () => {
        const { result } = renderHook(() => useListFilters('sales.index', initial, defaults));

        expect(result.current.values).toEqual(initial);
    });

    it('a select applies at once and drops the values left at their default', () => {
        const { result } = renderHook(() => useListFilters('sales.index', initial, defaults));

        act(() => result.current.set('sale_type', 'credit'));

        expect(result.current.values.sale_type).toBe('credit');
        expect(get).toHaveBeenCalledOnce();
        expect(get).toHaveBeenCalledWith(
            '/sales.index',
            { search: 'ana', date: '2026-10-20', sale_type: 'credit' },
            { preserveState: true, replace: true },
        );
    });

    it('typing only changes the draft until the form is submitted', () => {
        const { result } = renderHook(() => useListFilters('sales.index', initial, defaults));

        act(() => result.current.draft('search', ''));
        expect(get).not.toHaveBeenCalled();

        act(() => result.current.submit());
        expect(get).toHaveBeenCalledWith(
            '/sales.index',
            { search: undefined, date: '2026-10-20', sale_type: undefined },
            { preserveState: true, replace: true },
        );
    });

    it('changes several filters at once and applies them together', () => {
        const { result } = renderHook(() => useListFilters('sales.index', initial, defaults));

        act(() => result.current.setMany({ date: '', sale_type: 'all' }));

        expect(result.current.values).toEqual({ search: 'ana', date: '', sale_type: 'all' });
        expect(get).toHaveBeenCalledOnce();
        expect(get).toHaveBeenCalledWith('/sales.index', { search: 'ana', date: undefined, sale_type: undefined }, { preserveState: true, replace: true });
    });

    it('follows the filters the server echoes back after a visit', () => {
        const { result, rerender } = renderHook(({ echoed }) => useListFilters('routes.index', echoed, defaults), {
            initialProps: { echoed: initial },
        });

        act(() => result.current.setMany({ date: '' }));
        rerender({ echoed: { ...initial, date: '2026-10-28' } });

        expect(result.current.values.date).toBe('2026-10-28');
    });

    it('can also keep the scroll position', () => {
        const { result } = renderHook(() => useListFilters('users.index', { search: '' }, { search: '' }, { preserveScroll: true }));

        act(() => result.current.set('search', 'juan'));

        expect(get).toHaveBeenCalledWith('/users.index', { search: 'juan' }, { preserveState: true, preserveScroll: true, replace: true });
    });
});
