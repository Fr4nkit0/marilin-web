import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Button, { buttonClass } from './Button';

describe('Button', () => {
    it('is a plain button, not a form submit, unless asked', () => {
        render(<Button>Guardar</Button>);

        expect(screen.getByRole('button', { name: 'Guardar' }).getAttribute('type')).toBe('button');
    });

    it('cannot be pressed twice while its form is being sent', () => {
        render(
            <Button type="submit" processing>
                Guardar
            </Button>,
        );

        const button = screen.getByRole('button', { name: 'Guardar' }) as HTMLButtonElement;
        expect(button.disabled).toBe(true);
        expect(button.getAttribute('aria-busy')).toBe('true');
    });

    it('primary actions are sky-600, the design system primary', () => {
        expect(buttonClass('primary')).toContain('bg-sky-600');
        expect(buttonClass('danger')).toContain('bg-rose-600');
        expect(buttonClass('secondary')).toContain('bg-canvas');
    });
});
