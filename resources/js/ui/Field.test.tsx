import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Checkbox, Field, FieldError, Input, Label } from './Field';

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

    it('the loose pieces work on their own, and an empty error renders nothing', () => {
        const { container } = render(
            <div>
                <Label htmlFor="remember" value="Recordarme" />
                <Checkbox id="remember" />
                <FieldError message={undefined} />
            </div>,
        );

        expect((screen.getByLabelText('Recordarme') as HTMLInputElement).type).toBe('checkbox');
        expect(container.querySelectorAll('p')).toHaveLength(0);
    });
});
