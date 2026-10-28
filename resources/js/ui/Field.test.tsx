import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Field, Input } from './Field';

describe('Field', () => {
    it('ties its label to the input inside it', () => {
        render(
            <Field label="Nombre" htmlFor="name">
                <Input id="name" />
            </Field>,
        );

        expect(screen.getByLabelText('Nombre').tagName).toBe('INPUT');
    });

    it('shows the validation error under the input and marks it invalid', () => {
        render(
            <Field label="Monto" htmlFor="amount" error="El monto es obligatorio.">
                <Input id="amount" invalid />
            </Field>,
        );

        expect(screen.getByText('El monto es obligatorio.')).toBeTruthy();
        expect(screen.getByLabelText('Monto').getAttribute('aria-invalid')).toBe('true');
    });
});
