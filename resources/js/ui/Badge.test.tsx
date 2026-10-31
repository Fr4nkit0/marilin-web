import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PaymentMethodBadge } from './Badge';

describe('PaymentMethodBadge', () => {
    it('shows every method of a sale paid with more than one', () => {
        render(<PaymentMethodBadge method="Efectivo + Transf." />);

        expect(screen.getByText('Efectivo + Transf.')).toBeTruthy();
    });

    it('names a single method by its kind', () => {
        render(
            <>
                <PaymentMethodBadge method="Transferencia Bancaria" />
                <PaymentMethodBadge method="Tarjeta de Débito/Crédito" />
                <PaymentMethodBadge method="Crédito Ruta" />
            </>,
        );

        expect(screen.getByText('Transferencia')).toBeTruthy();
        expect(screen.getByText('Tarjeta')).toBeTruthy();
        expect(screen.getByText('Crédito / Cta Cte')).toBeTruthy();
    });
});
