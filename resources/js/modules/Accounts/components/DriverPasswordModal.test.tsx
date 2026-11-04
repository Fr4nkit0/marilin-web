import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DriverAccount } from '@/modules/Accounts/types';
import DriverPasswordModal from './DriverPasswordModal';

function driver(id: number, name: string): DriverAccount {
    return { id, name, username: `driver${id}`, phone_number: null, enabled: true, created_at: null };
}

function modal(account: DriverAccount | null, onClose: () => void = () => {}) {
    return <DriverPasswordModal account={account} onClose={onClose} />;
}

describe('DriverPasswordModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string, id: number) => `/${name}/${id}`);
        // The server accepts the new password at once.
        vi.spyOn(router, 'patch').mockImplementation((_url, _data, options) => {
            void options?.onSuccess?.({} as never);
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('opens empty for the next driver, never with the password just set for another one', async () => {
        const onClose = vi.fn();
        const { rerender } = render(modal(driver(1, 'Lucía Fernández'), onClose));

        fireEvent.change(screen.getByLabelText('Contraseña nueva'), { target: { value: 'secret-123' } });
        fireEvent.change(screen.getByLabelText('Repetir contraseña'), { target: { value: 'secret-123' } });
        fireEvent.click(screen.getByRole('button', { name: 'Cambiar contraseña' }));

        await vi.waitFor(() => expect(onClose).toHaveBeenCalledOnce());
        expect(router.patch).toHaveBeenCalledWith(
            '/users.password/1',
            { password: 'secret-123', password_confirmation: 'secret-123' },
            expect.anything(),
        );

        rerender(modal(null));
        rerender(modal(driver(2, 'Martín Gómez')));

        await vi.waitFor(() => {
            expect((screen.getByLabelText('Contraseña nueva') as HTMLInputElement).value).toBe('');
            expect((screen.getByLabelText('Repetir contraseña') as HTMLInputElement).value).toBe('');
        });
    });
});
