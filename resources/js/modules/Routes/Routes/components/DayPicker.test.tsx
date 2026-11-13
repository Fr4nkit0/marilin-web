import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import DayPicker from './DayPicker';

describe('DayPicker', () => {
    it('shows the chosen day in a date field', () => {
        render(<DayPicker value="2026-11-13" onChange={() => {}} />);

        expect((screen.getByLabelText('Fecha') as HTMLInputElement).value).toBe('2026-11-13');
    });

    it('moves to the day before and the day after, across a month', () => {
        const onChange = vi.fn();
        render(<DayPicker value="2026-11-30" onChange={onChange} />);

        fireEvent.click(screen.getByRole('button', { name: 'Día siguiente' }));
        fireEvent.click(screen.getByRole('button', { name: 'Día anterior' }));

        expect(onChange.mock.calls).toEqual([['2026-12-01'], ['2026-11-29']]);
    });

    it('applies a day typed or picked in the field, and ignores a cleared field', () => {
        const onChange = vi.fn();
        render(<DayPicker value="2026-11-13" onChange={onChange} />);
        const field = screen.getByLabelText('Fecha');

        fireEvent.change(field, { target: { value: '2026-11-12' } });
        fireEvent.change(field, { target: { value: '' } });

        expect(onChange.mock.calls).toEqual([['2026-11-12']]);
    });
});
