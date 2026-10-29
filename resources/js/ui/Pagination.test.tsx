import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Pagination from './Pagination';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...rest }: { href: string; children?: ReactNode; [key: string]: unknown }) => {
        const { preserveState: _preserveState, ...attributes } = rest;
        return (
            <a href={href} {...attributes}>
                {children}
            </a>
        );
    },
}));

const page = (lastPage: number) => ({
    data: [],
    current_page: 2,
    last_page: lastPage,
    per_page: 15,
    total: 40,
    links: [
        { url: '/sales?page=1', label: '&laquo; Anterior', active: false },
        { url: '/sales?page=2', label: '2', active: true },
        { url: null, label: 'Siguiente &raquo;', active: false },
    ],
});

describe('Pagination', () => {
    it('shows nothing when everything fits in one page', () => {
        const { container } = render(<Pagination page={page(1)} noun="ventas" />);

        expect(container.innerHTML).toBe('');
    });

    it('shows where you are and a link per page', () => {
        render(<Pagination page={page(3)} noun="ventas" />);

        expect(screen.getByText('Página 2 de 3 (40 ventas)')).toBeTruthy();
        expect(screen.getByText('« Anterior').getAttribute('href')).toBe('/sales?page=1');
        expect(screen.getByText('2').getAttribute('aria-current')).toBe('page');
        expect(screen.getByText('Siguiente »').getAttribute('href')).toBe('#');
    });

    it('labels the previous and next links in Spanish whatever the server locale', () => {
        const english = {
            ...page(3),
            links: [
                { url: '/sales?page=1', label: '&laquo; Previous', active: false },
                { url: '/sales?page=1', label: '1', active: false },
                { url: '/sales?page=2', label: '2', active: true },
                { url: '/sales?page=3', label: 'Next &raquo;', active: false },
            ],
        };
        render(<Pagination page={english} />);

        expect(screen.getByText('« Anterior').getAttribute('href')).toBe('/sales?page=1');
        expect(screen.getByText('Siguiente »').getAttribute('href')).toBe('/sales?page=3');
        expect(screen.queryByText(/Previous|Next/)).toBeNull();
    });

    it('can leave out the total', () => {
        render(<Pagination page={page(3)} />);

        expect(screen.getByText('Página 2 de 3')).toBeTruthy();
    });
});
