import { balanceLabel, balanceState, type BalanceState } from '@/lib/format';

export type StatusVariant = 'en_ruta' | 'finalizado' | 'pendiente' | 'parado' | 'cancelado' | 'sin_actividad' | 'en_progreso';

export interface StatusBadgeProps {
    status: StatusVariant | string;
    label?: string;
    className?: string;
}

export function StatusBadge({ status, label, className = '' }: StatusBadgeProps) {
    const s = status.toLowerCase().replace(/\s+/g, '_');

    let badgeClass = 'bg-surface-1 text-ink-secondary border-hairline/60';
    let text = label || status;
    text = text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();

    switch (s) {
        case 'en_ruta':
        case 'in_progress':
        case 'en_progreso':
            badgeClass = 'bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30';
            text = label || 'En ruta';
            break;
        case 'finalizado':
        case 'completed':
        case 'completado':
        case 'delivered':
        case 'entregado':
            badgeClass = 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30';
            text = label || 'Finalizado';
            break;
        case 'pendiente':
        case 'planned':
        case 'planificado':
            badgeClass = 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200/60 dark:border-amber-500/30';
            text = label || 'Pendiente';
            break;
        case 'parado':
        case 'paused':
            badgeClass = 'bg-orange-50 dark:bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-200/60 dark:border-orange-500/30';
            text = label || 'Parado';
            break;
        case 'cancelado':
        case 'cancelled':
        case 'failed':
            badgeClass = 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200/60 dark:border-rose-500/30';
            text = label || 'Cancelado';
            break;
        case 'sin_actividad':
            badgeClass = 'bg-canvas-soft text-ink-secondary border-hairline/60';
            text = label || 'Sin actividad';
            break;
    }

    return (
        <span
            className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${badgeClass} ${className}`}
        >
            <span className="w-1.5 h-1.5 rounded-full bg-current"></span>
            {text}
        </span>
    );
}

export interface DebtBadgeProps {
    balance: number | string;
    className?: string;
}

const BALANCE_TINTS: Record<BalanceState, string> = {
    debt: 'border-rose-200/60 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300',
    clear: 'border-emerald-200/60 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    credit: 'border-sky-200/60 dark:border-sky-500/30 bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300',
};

/** Con deuda, Al día or Saldo a favor, from the sign of the customer's balance. */
export function DebtBadge({ balance, className = '' }: DebtBadgeProps) {
    return (
        <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${BALANCE_TINTS[balanceState(balance)]} ${className}`}
        >
            {balanceLabel(balance)}
        </span>
    );
}

export interface PaymentMethodBadgeProps {
    method: 'cash' | 'transfer' | 'credit' | 'efectivo' | 'transferencia' | 'cuenta_corriente' | string;
    className?: string;
}

export function PaymentMethodBadge({ method, className = '' }: PaymentMethodBadgeProps) {
    const m = method.toLowerCase();
    let label = 'Efectivo';
    let style = 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200/60 dark:border-emerald-500/30';

    if (method.includes(' + ')) {
        // A sale paid with more than one method ("Efectivo + Transf."): the label is already the methods.
        label = method;
        style = 'bg-surface-1 text-ink-secondary border-hairline/60';
    } else if (m.includes('transf')) {
        label = 'Transferencia';
        style = 'bg-sky-50 dark:bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-200/60 dark:border-sky-500/30';
    } else if (m.includes('tarjeta') || m.includes('card')) {
        label = 'Tarjeta';
        style = 'bg-indigo-50 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-200/60 dark:border-indigo-500/30';
    } else if (m.includes('credit') || m.includes('crédito') || m.includes('cuenta') || m.includes('cte')) {
        label = 'Crédito / Cta Cte';
        style = 'bg-purple-50 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200/60 dark:border-purple-500/30';
    }

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${style} ${className}`}>
            {label}
        </span>
    );
}
