import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Toast from './Toast';

describe('Toast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the message in a polite live region', () => {
        render(<Toast toast={{ id: 1, text: 'Venta en casa registrada.' }} onDismiss={() => {}} />);

        const region = screen.getByRole('status');
        expect(region.getAttribute('aria-live')).toBe('polite');
        expect(region.textContent).toContain('Venta en casa registrada.');
    });

    it('renders an empty live region when there is nothing to say', () => {
        render(<Toast toast={null} onDismiss={() => {}} />);

        expect(screen.getByRole('status').textContent).toBe('');
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('dismisses itself after the timeout', () => {
        const onDismiss = vi.fn();
        render(<Toast toast={{ id: 1, text: 'Precio actualizado' }} onDismiss={onDismiss} duration={4000} />);

        act(() => vi.advanceTimersByTime(3999));
        expect(onDismiss).not.toHaveBeenCalled();

        act(() => vi.advanceTimersByTime(1));
        expect(onDismiss).toHaveBeenCalledTimes(1);
    });

    it('closes with its button', () => {
        const onDismiss = vi.fn();
        render(<Toast toast={{ id: 1, text: 'Precio actualizado' }} onDismiss={onDismiss} />);

        fireEvent.click(screen.getByRole('button', { name: 'Cerrar aviso' }));

        expect(onDismiss).toHaveBeenCalledTimes(1);
    });

    it('a new message with the same text restarts the timeout', () => {
        const onDismiss = vi.fn();
        const { rerender } = render(<Toast toast={{ id: 1, text: 'Carga registrada' }} onDismiss={onDismiss} duration={4000} />);

        act(() => vi.advanceTimersByTime(3000));
        rerender(<Toast toast={{ id: 2, text: 'Carga registrada' }} onDismiss={onDismiss} duration={4000} />);
        act(() => vi.advanceTimersByTime(3000));
        expect(onDismiss).not.toHaveBeenCalled();
        expect(screen.getByRole('status').textContent).toContain('Carga registrada');

        act(() => vi.advanceTimersByTime(1000));
        expect(onDismiss).toHaveBeenCalledTimes(1);
    });
});
