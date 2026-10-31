import { describe, expect, it } from 'vitest';
import {
    accountEntryTypeLabel,
    balanceLabel,
    balanceState,
    date,
    dateTime,
    money,
    paymentMethodLabel,
    paymentMethodsLabel,
    paymentStatusLabel,
    stopResultLabel,
    stopStatusLabel,
    time,
    timeWindowLabel,
    weekdayName,
} from './format';

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
        expect(dateTime(at)).toBe('20/10/2026 13:05');
    });

    it('shows a plain day as dd/mm/aaaa, the same day in any zone', () => {
        expect(date('2026-10-29')).toBe('29/10/2026');
        expect(date('2026-01-05')).toBe('05/01/2026');
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
        expect(paymentStatusLabel('CANCELLED')).toBe('Anulado');
        expect(paymentStatusLabel('ANNULLED')).toBe('Anulado');
    });

    it('names every customer account entry type', () => {
        expect(accountEntryTypeLabel('SALE')).toBe('Venta');
        expect(accountEntryTypeLabel('PAYMENT')).toBe('Cobro');
        expect(accountEntryTypeLabel('ADJUSTMENT')).toBe('Ajuste');
        expect(accountEntryTypeLabel('CREDIT_NOTE')).toBe('Nota de crédito');
    });
});

describe('stop result', () => {
    it('shows what happened at the visit', () => {
        expect(stopResultLabel({ outcome: 'Ausente', done: true })).toBe('Ausente');
        expect(stopResultLabel({ outcome: 'Ausente', done: false })).toBe('Ausente');
    });

    it('reads "Sin novedades" only for a visited stop without an outcome', () => {
        expect(stopResultLabel({ outcome: null, done: true })).toBe('Sin novedades');
    });

    it('shows a dash for a stop not visited yet', () => {
        expect(stopResultLabel({ outcome: null, done: false })).toBe('—');
        expect(stopResultLabel({ done: false })).toBe('—');
    });
});

describe('payment methods', () => {
    it('names a method by its kind, whatever its full name', () => {
        expect(paymentMethodLabel('Efectivo')).toBe('Efectivo');
        expect(paymentMethodLabel('Transferencia Bancaria')).toBe('Transferencia');
        expect(paymentMethodLabel('Tarjeta de Débito/Crédito')).toBe('Tarjeta');
        expect(paymentMethodLabel('Mercado Pago')).toBe('Mercado Pago');
    });

    it('shows the only method of a sale in full', () => {
        expect(paymentMethodsLabel(['Transferencia Bancaria'])).toBe('Transferencia');
        expect(paymentMethodsLabel(['Efectivo', 'Efectivo'])).toBe('Efectivo');
    });

    it('shows every method of a mixed payment, short and in the order paid', () => {
        expect(paymentMethodsLabel(['Efectivo', 'Transferencia Bancaria'])).toBe('Efectivo + Transf.');
        expect(paymentMethodsLabel(['Transferencia', 'Efectivo', 'Tarjeta de Débito/Crédito', 'Efectivo'])).toBe('Transf. + Efectivo + Tarjeta');
        expect(paymentMethodsLabel(['Efectivo', 'Mercado Pago'])).toBe('Efectivo + Mercado Pago');
    });

    it('is empty for a sale without payments', () => {
        expect(paymentMethodsLabel([])).toBe('');
    });
});

describe('balance', () => {
    it('reads a positive balance as debt', () => {
        expect(balanceState(6500)).toBe('debt');
        expect(balanceLabel('6500.00')).toBe('Con deuda ($6.500)');
    });

    it('reads a zero balance as up to date', () => {
        expect(balanceState(0)).toBe('clear');
        expect(balanceState('0.00')).toBe('clear');
        expect(balanceLabel(0)).toBe('Al día');
    });

    it('reads a negative balance as credit in favor of the customer, in absolute value', () => {
        expect(balanceState(-6500)).toBe('credit');
        expect(balanceLabel('-6500.00')).toBe('Saldo a favor $6.500');
    });
});

describe('delivery preferences', () => {
    it('names the days from 0 = Domingo to 6 = Sábado', () => {
        expect([0, 1, 2, 3, 4, 5, 6].map(weekdayName)).toEqual(['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']);
    });

    it('shows a window from start to end, or the whole day without one', () => {
        expect(timeWindowLabel('08:00', '12:00')).toBe('08:00 a 12:00');
        expect(timeWindowLabel(null, null)).toBe('Todo el día');
    });

    it('shows a window open at one end', () => {
        expect(timeWindowLabel('08:00', null)).toBe('Desde 08:00');
        expect(timeWindowLabel(null, '12:00')).toBe('Hasta 12:00');
    });
});
