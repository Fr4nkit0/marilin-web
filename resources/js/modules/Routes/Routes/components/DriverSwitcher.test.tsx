import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import DriverSwitcher from './DriverSwitcher';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn() } }));

const drivers = [
    { id: 1, name: 'Ana Torres' },
    { id: 2, name: 'Carlos Gómez' },
    { id: 3, name: 'Juan Pérez' },
];

describe('DriverSwitcher', () => {
    beforeEach(() => {
        vi.mocked(router.get).mockClear();
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
    });

    it('shows the current driver among every driver', () => {
        render(<DriverSwitcher drivers={drivers} currentId={2} date="2026-11-13" />);
        const select = screen.getByRole('combobox', { name: 'Cambiar de repartidor' }) as HTMLSelectElement;

        expect(select.value).toBe('2');
        expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual([
            'Ana Torres',
            'Carlos Gómez',
            'Juan Pérez',
        ]);
    });

    it('opens the picked driver on the same day', () => {
        render(<DriverSwitcher drivers={drivers} currentId={2} date="2026-11-13" />);

        fireEvent.change(screen.getByRole('combobox', { name: 'Cambiar de repartidor' }), { target: { value: '3' } });

        expect(router.get).toHaveBeenCalledWith('/drivers.show/3', { date: '2026-11-13' });
    });
});
