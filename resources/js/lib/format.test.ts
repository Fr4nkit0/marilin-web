import { describe, expect, it } from 'vitest';
import { date, dateTime, money, paymentStatusLabel, stopStatusLabel, time } from './format';

describe('money', () => {
    it('shows pesos with the Argentine thousands separator', () => {
        expect(money(1500)).toBe('$1.500');
        expect(money(6500)).toBe('$6.500');
        expect(money(0)).toBe('$0');
    });

    it('keeps cents only when there are any', () => {
        expect(money(1250.5)).toBe('$1.250,5');
        expect(money(400.51)).toBe('$400,51');
    });

    it('accepts the decimal strings the server sends', () => {
        expect(money('1250.50')).toBe('$1.250,5');
        expect(money('700.00')).toBe('$700');
    });
});

describe('dates', () => {
    const at = '2026-10-20T13:05:00-03:00';

    it('shows the hour and minute of a moment', () => {
        expect(time(at)).toBe('13:05');
    });

    it('shows the day of a moment', () => {
        expect(date(at)).toBe('20/10/2026');
    });

    it('shows day and hour together', () => {
        expect(dateTime(at)).toBe('20/10/26, 13:05');
    });

    it('reads the server timestamps that come with a space instead of a T', () => {
        expect(date('2026-10-20 13:05:00')).toBe('20/10/2026');
    });
});

describe('status labels', () => {
    it('names every stop status', () => {
        expect(stopStatusLabel('PENDING')).toBe('Pendiente');
        expect(stopStatusLabel('IN_PROGRESS')).toBe('En curso');
        expect(stopStatusLabel('FINISHED')).toBe('Terminada');
        expect(stopStatusLabel('RESCHEDULED')).toBe('Reprogramada');
        expect(stopStatusLabel('CANCELLED')).toBe('Cancelada');
    });

    it('names payment statuses', () => {
        expect(paymentStatusLabel('CONFIRMED')).toBe('Confirmado');
        expect(paymentStatusLabel('CANCELLED')).toBe('Cancelado');
    });
});
