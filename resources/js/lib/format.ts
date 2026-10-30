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

/** Anything but a confirmed payment reads as cancelled, as the payments list always showed it. */
export function paymentStatusLabel(status: PaymentStatus): string {
    return status === 'CONFIRMED' ? 'Confirmado' : 'Cancelado';
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
