/**
 * How the admin web shows money, dates and statuses. Every page and modal formats through here,
 * so a change to how pesos or hours look is made once.
 */

export type StopStatus = 'PENDING' | 'IN_PROGRESS' | 'FINISHED' | 'RESCHEDULED' | 'CANCELLED';

export type PaymentStatus = 'CONFIRMED' | 'CANCELLED' | 'ANNULLED';

export type AccountEntryType = 'SALE' | 'PAYMENT' | 'ADJUSTMENT' | 'CREDIT_NOTE';

/** A customer's balance: owes money, owes nothing, or has paid more than owed (a negative balance). */
export type BalanceState = 'debt' | 'clear' | 'credit';

const STOP_STATUS_LABELS: Record<StopStatus, string> = {
    PENDING: 'Pendiente',
    IN_PROGRESS: 'En curso',
    FINISHED: 'Terminada',
    RESCHEDULED: 'Reprogramada',
    CANCELLED: 'Cancelada',
};

const ACCOUNT_ENTRY_TYPE_LABELS: Record<AccountEntryType, string> = {
    SALE: 'Venta',
    PAYMENT: 'Cobro',
    ADJUSTMENT: 'Ajuste',
    CREDIT_NOTE: 'Nota de crédito',
};

/** Pesos with the Argentine separators; cents only when there are any ("$1.500", "$1.250,5"). */
export function money(amount: number | string): string {
    return `$${Number(amount).toLocaleString('es-AR', { maximumFractionDigits: 2 })}`;
}

/** Hour and minute of a moment, in the browser's zone, 24-hour clock ("13:05"). */
export function time(moment: string): string {
    return parse(moment).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
}

/** The day of a moment or of a plain day, as dd/mm/aaaa ("29/10/2026", "05/01/2026"). */
export function date(moment: string): string {
    return parse(moment).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/** Day and hour of a moment ("20/10/2026 13:05"). */
export function dateTime(moment: string): string {
    return `${date(moment)} ${time(moment)}`;
}

export function stopStatusLabel(status: StopStatus): string {
    return STOP_STATUS_LABELS[status];
}

/**
 * What dispatch shows as a stop's result: the visit's outcome, "Sin novedades" for a visited stop without one,
 * and a dash for a stop not visited yet.
 */
export function stopResultLabel(stop: { outcome?: string | null; done: boolean }): string {
    if (stop.outcome) return stop.outcome;
    return stop.done ? 'Sin novedades' : '—';
}

export function accountEntryTypeLabel(type: AccountEntryType): string {
    return ACCOUNT_ENTRY_TYPE_LABELS[type];
}

export function balanceState(balance: number | string): BalanceState {
    const amount = Number(balance);
    if (amount > 0) return 'debt';
    if (amount < 0) return 'credit';
    return 'clear';
}

/** "Con deuda ($6.500)", "Al día" or, for a negative balance, "Saldo a favor $6.500". */
export function balanceLabel(balance: number | string): string {
    const amount = Number(balance);
    switch (balanceState(amount)) {
        case 'debt':
            return `Con deuda (${money(amount)})`;
        case 'credit':
            return `Saldo a favor ${money(Math.abs(amount))}`;
        default:
            return 'Al día';
    }
}

const WEEKDAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

/** The day of a delivery preference, 0 = Domingo to 6 = Sábado. */
export function weekdayName(dayOfWeek: number): string {
    return WEEKDAYS[dayOfWeek] ?? '';
}

/** A delivery window as hh:mm: "08:00 a 12:00", "Desde 08:00", "Hasta 12:00", or "Todo el día" without one. */
export function timeWindowLabel(start: string | null, end: string | null): string {
    if (start && end) return `${start} a ${end}`;
    if (start) return `Desde ${start}`;
    if (end) return `Hasta ${end}`;
    return 'Todo el día';
}

/**
 * A payment is in force from the moment it is recorded (stored as CONFIRMED; nobody confirms it), so only an annulled
 * one carries a label: the web says "Anulado" for both stored values.
 */
export function paymentStatusLabel(status: PaymentStatus): string | null {
    return status === 'CONFIRMED' ? null : 'Anulado';
}

type PaymentMethodKind = 'cash' | 'transfer';

const PAYMENT_METHOD_NAMES: Record<PaymentMethodKind, { full: string; short: string }> = {
    cash: { full: 'Efectivo', short: 'Efectivo' },
    transfer: { full: 'Transferencia', short: 'Transf.' },
};

/** The kind of a payment method from its configured name ("Transferencia Bancaria"). */
function paymentMethodKind(name: string): PaymentMethodKind | null {
    const lower = name.toLowerCase();
    if (lower.includes('efectivo')) return 'cash';
    if (lower.includes('transf')) return 'transfer';
    return null;
}

/** A payment method by its kind: "Efectivo", "Transferencia"; any other method by its own name. */
export function paymentMethodLabel(name: string): string {
    const kind = paymentMethodKind(name);
    return kind ? PAYMENT_METHOD_NAMES[kind].full : name;
}

/**
 * Every method a sale was paid with, in the order paid: one in full ("Transferencia"), several short and joined
 * ("Efectivo + Transf."); empty when the sale has no payment.
 */
export function paymentMethodsLabel(names: string[]): string {
    const labels = [...new Set(names.map(paymentMethodLabel))];
    if (labels.length <= 1) return labels[0] ?? '';

    return labels
        .map((label) => {
            const kind = paymentMethodKind(label);
            return kind ? PAYMENT_METHOD_NAMES[kind].short : label;
        })
        .join(' + ');
}

/**
 * A Salta phone as the server stores it, "387 587-4823", formatted while it is typed. A complete number with the
 * country prefix (54, 549) or the trunk 0 drops it; one still being typed with a prefix, or longer than ten digits,
 * is left as typed.
 */
export function formatPhone(value: string): string {
    const digits = value.replace(/\D/g, '');
    const local =
        digits.length === 13 && digits.startsWith('549')
            ? digits.slice(3)
            : digits.length === 12 && digits.startsWith('54')
              ? digits.slice(2)
              : digits.length === 11 && digits.startsWith('0')
                ? digits.slice(1)
                : digits;

    if (local.length === 10) return `${local.slice(0, 3)} ${local.slice(3, 6)}-${local.slice(6)}`;
    if (local.length > 10 || local === '' || /^\s*[+0]/.test(value)) return value;

    return local.slice(0, 3) + (local.length > 3 ? ` ${local.slice(3, 6)}` : '') + (local.length > 6 ? `-${local.slice(6)}` : '');
}

/**
 * The server sends ISO 8601, and some timestamps with a space instead of the "T".
 * A plain day ("2026-10-29") is read as local midnight, not UTC, so it never shows as the day before.
 */
function parse(moment: string): Date {
    const plainDay = /^(\d{4})-(\d{2})-(\d{2})$/.exec(moment);
    if (plainDay) {
        return new Date(Number(plainDay[1]), Number(plainDay[2]) - 1, Number(plainDay[3]));
    }

    return new Date(moment.replace(' ', 'T'));
}
