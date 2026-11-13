import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import SplitGroupCard from './SplitGroupCard';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));

const group = {
    neighborhood: 'centro',
    name: 'Centro',
    stops: [
        { id: 1, customer: 'Ana', street: 'Mitre 10', neighborhood: 'centro', scheduledTime: null },
        { id: 2, customer: 'Juan', street: null, neighborhood: 'centro', scheduledTime: '09:00' },
    ],
    suggestions: [],
};

describe('SplitGroupCard', () => {
    it('lets a phone select every stop, since the header with the select-all box is hidden there', () => {
        render(<SplitGroupCard routeId={1} group={group} drivers={[]} onAssign={() => {}} />);
        fireEvent.click(screen.getByRole('button', { name: /Elegir paradas/ }));

        const phoneSelectAll = screen.getByRole('checkbox', { name: 'Seleccionar todas' });
        expect(phoneSelectAll.closest('.sm\\:hidden')).not.toBeNull();

        fireEvent.click(phoneSelectAll);

        expect((screen.getByRole('checkbox', { name: 'Seleccionar Ana' }) as HTMLInputElement).checked).toBe(true);
        expect((screen.getByRole('checkbox', { name: 'Seleccionar Juan' }) as HTMLInputElement).checked).toBe(true);
        // The header box (desktop) follows the same selection.
        expect((screen.getByRole('checkbox', { name: 'Seleccionar todas las paradas de Centro' }) as HTMLInputElement).checked).toBe(true);
    });
});
