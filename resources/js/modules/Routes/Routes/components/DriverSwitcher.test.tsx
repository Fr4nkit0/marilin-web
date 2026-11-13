import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DriverSwitcher from './DriverSwitcher';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const drivers = [
    { id: 1, name: 'Ana Torres' },
    { id: 2, name: 'Carlos Gómez' },
    { id: 3, name: 'Juan Pérez' },
];

describe('DriverSwitcher', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, params: { repartidor: number; date: string }) => `/${name}/${params.repartidor}?date=${params.date}`);
    });

    it('shows the current driver as the heading, with one button that opens the list', () => {
        render(<DriverSwitcher drivers={drivers} currentId={2} date="2026-11-13" />);

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Carlos Gómez');
        expect(screen.getByRole('button', { name: 'Carlos Gómez, cambiar de repartidor' })).toBeTruthy();
        expect(screen.queryByRole('menu')).toBeNull();
        expect(screen.queryByRole('combobox')).toBeNull();
    });

    it('lists every driver, the current one marked, each opening that driver on the same day', () => {
        render(<DriverSwitcher drivers={drivers} currentId={2} date="2026-11-13" />);

        fireEvent.click(screen.getByRole('button', { name: 'Carlos Gómez, cambiar de repartidor' }));
        const items = screen.getAllByRole('menuitem');

        expect(items.map((item) => item.textContent)).toEqual(['Ana Torres', 'Carlos Gómez', 'Juan Pérez']);
        expect(items.map((item) => item.getAttribute('href'))).toEqual([
            '/drivers.show/1?date=2026-11-13',
            '/drivers.show/2?date=2026-11-13',
            '/drivers.show/3?date=2026-11-13',
        ]);
        expect(items.map((item) => item.getAttribute('aria-current'))).toEqual([null, 'page', null]);
    });
});
