/**
 * How the admin web shows money, dates and statuses. Every page and modal formats through here,
 * so a change to how pesos or hours look is made once.
 */

export type StopStatus = 'PENDING' | 'IN_PROGRESS' | 'FINISHED' | 'RESCHEDULED' | 'CANCELLED';

export type PaymentStatus = 'CONFIRMED' | 'CANCELLED' | 'ANNULLED';

const STOP_STATUS_LABELS: Record<StopStatus, string> = {
    PENDING: 'Pendiente',
    IN_PROGRESS: 'En curso',
    FINISHED: 'Terminada',
    RESCHEDULED: 'Reprogramada',
    CANCELLED: 'Cancelada',
};

/** Pesos with the Argentine separators; cents only when there are any ("$1.500", "$1.250,5"). */
export function money(amount: number | string): string {
    return `$${Number(amount).toLocaleString('es-AR', { maximumFractionDigits: 2 })}`;
}

/** Hour and minute of a moment, in the browser's zone, 24-hour clock ("13:05"). */
export function time(moment: string): string {
    return parse(moment).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
}

/** The day of a moment ("20/10/2026"). */
export function date(moment: string): string {
    return parse(moment).toLocaleDateString('es-AR');
}

/** Day and hour of a moment ("20/10/26, 13:05"). */
export function dateTime(moment: string): string {
    return parse(moment).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short', hourCycle: 'h23' });
}

export function stopStatusLabel(status: StopStatus): string {
    return STOP_STATUS_LABELS[status];
}

/** Anything but a confirmed payment reads as cancelled, as the payments list always showed it. */
export function paymentStatusLabel(status: PaymentStatus): string {
    return status === 'CONFIRMED' ? 'Confirmado' : 'Cancelado';
}

/** The server sends ISO 8601, and some timestamps with a space instead of the "T". */
function parse(moment: string): Date {
    return new Date(moment.replace(' ', 'T'));
}
