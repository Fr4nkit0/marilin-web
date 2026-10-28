import React, { ReactNode } from 'react';
import { LucideIcon, TrendingUp, TrendingDown, Minus } from 'lucide-react';
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
            badgeClass = 'bg-blue-50 text-blue-700 border-blue-200/60';
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

export interface KpiCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: LucideIcon;
    trend?: {
        value: number | string;
        isPositive?: boolean;
        neutral?: boolean;
        label?: string;
    };
    variant?: 'primary' | 'accent' | 'success' | 'amber' | 'slate';
    className?: string;
    /** Makes the card a button, e.g. to open the records behind the figure. */
    onClick?: () => void;
}

export function KpiCard({
    title,
    value,
    subtitle,
    icon: Icon,
    trend,
    variant = 'primary',
    className = '',
    onClick,
}: KpiCardProps) {
    const iconColors = {
        primary: 'bg-blue-50 text-blue-600 border border-blue-100',
        accent: 'bg-orange-50 text-orange-600 border border-orange-100',
        success: 'bg-emerald-50 text-emerald-600 border border-emerald-100',
        amber: 'bg-amber-50 text-amber-600 border border-amber-100',
        slate: 'bg-slate-50 text-slate-600 border border-slate-100',
    };

    const Wrapper = onClick ? 'button' : 'div';

    return (
        <Wrapper
            {...(onClick ? { type: 'button' as const, onClick } : {})}
            className={`bg-white rounded-2xl border border-gray-200/60 p-5 shadow-sm transition-shadow hover:shadow-md ${
                onClick
                    ? 'w-full text-left cursor-pointer hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-600/30'
                    : ''
            } ${className}`}
        >
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500">{title}</p>
                <div className={`p-2 rounded-xl ${iconColors[variant]}`}>
                    <Icon className="w-4 h-4" strokeWidth={2} />
                </div>
            </div>

            <div className="mt-4 flex items-baseline justify-between">
                <h3 className="text-2xl font-semibold text-slate-900 tracking-tight">{value}</h3>
                {trend && (
                    <div
                        className={`flex items-center text-xs font-medium ${
                            trend.neutral
                                ? 'text-slate-500 bg-slate-50 border border-gray-200/60'
                                : trend.isPositive
                                ? 'text-emerald-700 bg-emerald-50 border border-emerald-200/60'
                                : 'text-rose-700 bg-rose-50 border border-rose-200/60'
                        } px-2 py-0.5 rounded-full`}
                    >
                        {trend.neutral ? (
                            <Minus className="w-3.5 h-3.5 mr-1" strokeWidth={2} />
                        ) : trend.isPositive ? (
                            <TrendingUp className="w-3.5 h-3.5 mr-1" strokeWidth={2} />
                        ) : (
                            <TrendingDown className="w-3.5 h-3.5 mr-1" strokeWidth={2} />
                        )}
                        <span>{trend.value}</span>
                    </div>
                )}
            </div>

            {(subtitle || trend?.label) && (
                <p className="mt-2 text-xs text-slate-400">{subtitle || trend?.label}</p>
            )}
            {onClick && <p className="mt-3 text-xs font-medium text-blue-600">Ver detalle →</p>}
        </Wrapper>
    );
}

export function PageHeader({
    title,
    description,
    actions,
    breadcrumbs,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    breadcrumbs?: { label: string; href?: string }[];
}) {
    return (
        <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <div className="mb-2 flex items-center gap-2 text-xs font-medium text-slate-500">
                        {breadcrumbs.map((b, idx) => (
                            <React.Fragment key={idx}>
                                {idx > 0 && <span className="text-slate-300">/</span>}
                                {b.href ? (
                                    <a href={b.href} className="hover:text-slate-900 transition-colors">
                                        {b.label}
                                    </a>
                                ) : (
                                    <span className="text-slate-700">{b.label}</span>
                                )}
                            </React.Fragment>
                        ))}
                    </div>
                )}
                <h1 className="text-2xl sm:text-3xl font-semibold text-slate-900 tracking-tight">{title}</h1>
                {description && <p className="text-sm text-slate-500 mt-1">{description}</p>}
            </div>
            {actions && <div className="flex items-center gap-3">{actions}</div>}
        </div>
    );
}
