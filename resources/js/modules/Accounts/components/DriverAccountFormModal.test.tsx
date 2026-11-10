import { router } from '@inertiajs/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import DriverAccountFormModal from './DriverAccountFormModal';

describe('DriverAccountFormModal', () => {
    beforeEach(() => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        vi.spyOn(router, 'visit').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('creates a driver profile only, with no way to pick another role', async () => {
        render(<DriverAccountFormModal show account={null} onClose={() => {}} />);

        expect(screen.getByText('Repartidor')).toBeTruthy();
        expect(screen.queryByRole('combobox')).toBeNull();

        fireEvent.change(screen.getByLabelText('Nombre'), { target: { value: 'Lucía Fernández' } });
        fireEvent.change(screen.getByLabelText('Usuario'), { target: { value: 'lfernandez' } });
        fireEvent.change(screen.getByLabelText('Contraseña'), { target: { value: 'secret-123' } });
        fireEvent.change(screen.getByLabelText('Repetir contraseña'), { target: { value: 'secret-123' } });
        fireEvent.click(screen.getByRole('button', { name: 'Crear repartidor' }));

        await vi.waitFor(() => expect(router.visit).toHaveBeenCalledOnce());
        const [url, options] = vi.mocked(router.visit).mock.calls[0];
        expect(url).toBe('/users.store');
        expect(options?.method).toBe('post');
        expect(options?.data).toEqual({
            name: 'Lucía Fernández',
            username: 'lfernandez',
            phone_number: null,
            password: 'secret-123',
            password_confirmation: 'secret-123',
        });
    });
});
