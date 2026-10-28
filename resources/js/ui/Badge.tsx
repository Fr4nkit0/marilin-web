import { money } from '@/lib/format';

export type StatusVariant = 'en_ruta' | 'finalizado' | 'pendiente' | 'parado' | 'cancelado' | 'sin_actividad' | 'en_progreso';

export interface StatusBadgeProps {
    status: StatusVariant | string;
    label?: string;
    className?: string;
}

export function StatusBadge({ status, label, className = '' }: StatusBadgeProps) {
    const s = status.toLowerCase().replace(/\s+/g, '_');

    let badgeClass = 'bg-slate-100 text-slate-700 border-gray-200/60';
    let text = label || status;
    text = text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();

    switch (s) {
        case 'en_ruta':
        case 'in_progress':
        case 'en_progreso':
            badgeClass = 'bg-indigo-50 text-indigo-700 border-indigo-200/60';
            text = label || 'En ruta';
            break;
        case 'finalizado':
        case 'completed':
        case 'completado':
        case 'delivered':
        case 'entregado':
            badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200/60';
            text = label || 'Finalizado';
            break;
        case 'pendiente':
        case 'planned':
        case 'planificado':
            badgeClass = 'bg-amber-50 text-amber-700 border-amber-200/60';
            text = label || 'Pendiente';
            break;
        case 'parado':
        case 'paused':
            badgeClass = 'bg-orange-50 text-orange-700 border-orange-200/60';
            text = label || 'Parado';
            break;
        case 'cancelado':
        case 'cancelled':
        case 'failed':
            badgeClass = 'bg-rose-50 text-rose-700 border-rose-200/60';
            text = label || 'Cancelado';
            break;
        case 'sin_actividad':
            badgeClass = 'bg-slate-50 text-slate-600 border-gray-200/60';
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
    hasDebt: boolean;
    balance?: number | string;
    className?: string;
}

export function DebtBadge({ hasDebt, balance, className = '' }: DebtBadgeProps) {
    if (hasDebt) {
        return (
            <span
                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-rose-200/60 bg-rose-50 text-rose-700 ${className}`}
            >
                Con deuda {balance ? `(${money(balance)})` : ''}
            </span>
        );
    }

    return (
        <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-emerald-200/60 bg-emerald-50 text-emerald-700 ${className}`}
        >
            Al día
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
    let style = 'bg-emerald-50 text-emerald-700 border-emerald-200/60';

    if (m.includes('transf')) {
        label = 'Transferencia';
        style = 'bg-sky-50 text-sky-700 border-sky-200/60';
    } else if (m.includes('credit') || m.includes('cuenta') || m.includes('cte')) {
        label = 'Crédito / Cta Cte';
        style = 'bg-purple-50 text-purple-700 border-purple-200/60';
    }

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${style} ${className}`}>
            {label}
        </span>
    );
}
