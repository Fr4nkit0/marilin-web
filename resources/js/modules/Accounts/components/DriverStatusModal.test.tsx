import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { DriverAccount } from '@/modules/Accounts/types';
import DriverStatusModal from './DriverStatusModal';

function driver(enabled: boolean, customersCount: number): DriverAccount {
    return { id: 1, name: 'Juan', username: 'juan', phone_number: null, enabled, created_at: null, customers_count: customersCount };
}

describe('DriverStatusModal', () => {
    it('warns how many customers disabling leaves without a driver', () => {
        render(<DriverStatusModal account={driver(true, 12)} onClose={() => {}} />);

        expect(screen.getByText('12 clientes quedarán sin repartidor.')).toBeTruthy();
    });

    it('says it in singular for one customer', () => {
        render(<DriverStatusModal account={driver(true, 1)} onClose={() => {}} />);

        expect(screen.getByText('1 cliente quedará sin repartidor.')).toBeTruthy();
    });

    it('does not warn when the driver has no customers or is being enabled', () => {
        const { unmount } = render(<DriverStatusModal account={driver(true, 0)} onClose={() => {}} />);
        expect(screen.queryByText(/sin repartidor/)).toBeNull();
        unmount();

        render(<DriverStatusModal account={driver(false, 3)} onClose={() => {}} />);
        expect(screen.queryByText(/sin repartidor/)).toBeNull();
    });
});
