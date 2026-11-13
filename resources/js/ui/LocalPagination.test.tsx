import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import LocalPagination, { pageOf } from './LocalPagination';

const items = Array.from({ length: 32 }, (_, index) => index + 1);

describe('pageOf', () => {
    it('cuts the list into pages of the given size', () => {
        expect(pageOf(items, 1, 15)).toEqual({ rows: items.slice(0, 15), page: 1, lastPage: 3 });
        expect(pageOf(items, 3, 15)).toEqual({ rows: [31, 32], page: 3, lastPage: 3 });
    });

    it('keeps the page inside the list when it shrinks or the page is out of range', () => {
        expect(pageOf(items, 9, 15).page).toBe(3);
        expect(pageOf(items, 0, 15).page).toBe(1);
        expect(pageOf([], 2, 15)).toEqual({ rows: [], page: 1, lastPage: 1 });
    });
});

describe('LocalPagination', () => {
    it('shows nothing when everything fits in one page', () => {
        const { container } = render(<LocalPagination page={1} lastPage={1} total={5} noun="paradas" onPage={() => {}} />);

        expect(container.firstChild).toBeNull();
    });

    it('says where you are and moves between pages', () => {
        const onPage = vi.fn();
        render(<LocalPagination page={2} lastPage={3} total={32} noun="paradas" onPage={onPage} />);

        expect(screen.getByText('Página 2 de 3 (32 paradas)')).toBeTruthy();
        expect(screen.getByRole('button', { name: '2' }).getAttribute('aria-current')).toBe('page');
        fireEvent.click(screen.getByRole('button', { name: /Anterior/ }));
        fireEvent.click(screen.getByRole('button', { name: /Siguiente/ }));
        fireEvent.click(screen.getByRole('button', { name: '3' }));

        expect(onPage.mock.calls).toEqual([[1], [3], [3]]);
    });

    it('cannot go before the first page or past the last', () => {
        const { rerender } = render(<LocalPagination page={1} lastPage={3} total={32} noun="paradas" onPage={() => {}} />);
        expect((screen.getByRole('button', { name: /Anterior/ }) as HTMLButtonElement).disabled).toBe(true);

        rerender(<LocalPagination page={3} lastPage={3} total={32} noun="paradas" onPage={() => {}} />);
        expect((screen.getByRole('button', { name: /Siguiente/ }) as HTMLButtonElement).disabled).toBe(true);
    });
});
