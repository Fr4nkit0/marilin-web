import { fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import NotificationsMenu from './NotificationsMenu';

vi.mock('@inertiajs/react', () => ({
    Link: ({ href, children, ...rest }: { href: string; children?: ReactNode; [key: string]: unknown }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

const items = [
    { key: 'driver-1', label: 'Juan Pérez está en ruta y nunca sincronizó', href: '/drivers/1' },
    { key: 'review', label: '3 registros esperan revisión', href: '/review' },
];

describe('NotificationsMenu', () => {
    it('is a named button that shows the dot only when there is something to see', () => {
        const { rerender } = render(<NotificationsMenu items={items} />);

        const bell = screen.getByRole('button', { name: 'Notificaciones' });
        expect(bell.getAttribute('aria-expanded')).toBe('false');
        expect(screen.getByTestId('notifications-dot')).toBeTruthy();

        rerender(<NotificationsMenu items={[]} />);
        expect(screen.queryByTestId('notifications-dot')).toBeNull();
    });

    it('opens a list of links to each screen', () => {
        render(<NotificationsMenu items={items} />);

        fireEvent.click(screen.getByRole('button', { name: 'Notificaciones' }));

        expect(screen.getByRole('button', { name: 'Notificaciones' }).getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('link', { name: 'Juan Pérez está en ruta y nunca sincronizó' }).getAttribute('href')).toBe('/drivers/1');
        expect(screen.getByRole('link', { name: '3 registros esperan revisión' }).getAttribute('href')).toBe('/review');
    });

    it('says there is nothing new when the list is empty', () => {
        render(<NotificationsMenu items={[]} />);

        fireEvent.click(screen.getByRole('button', { name: 'Notificaciones' }));

        expect(screen.getByText('No hay novedades.')).toBeTruthy();
        expect(screen.queryAllByRole('link')).toHaveLength(0);
    });

    it('closes with Escape and gives the focus back to the bell', () => {
        render(<NotificationsMenu items={items} />);
        const bell = screen.getByRole('button', { name: 'Notificaciones' });

        fireEvent.click(bell);
        fireEvent.keyDown(screen.getByRole('link', { name: '3 registros esperan revisión' }), { key: 'Escape' });

        expect(screen.queryByRole('link')).toBeNull();
        expect(bell.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(bell);
    });
});
