import React, { ReactNode } from 'react';
import { LucideIcon, TrendingUp, TrendingDown, Minus } from 'lucide-react';

export type StatusVariant = 'en_ruta' | 'finalizado' | 'pendiente' | 'parado' | 'cancelado' | 'sin_actividad' | 'en_progreso';

export interface StatusBadgeProps {
    status: StatusVariant | string;
    label?: string;
    className?: string;
}

export function StatusBadge({ status, label, className = '' }: StatusBadgeProps) {
    const s = status.toLowerCase().replace(/\s+/g, '_');

    let badgeClass = 'bg-slate-100 text-slate-700 border-slate-200';
    let text = label || status;

    switch (s) {
        case 'en_ruta':
        case 'in_progress':
        case 'en_progreso':
            badgeClass = 'bg-blue-50 text-blue-700 border-blue-200';
            text = label || 'En Ruta';
            break;
        case 'finalizado':
        case 'completed':
        case 'completado':
        case 'delivered':
        case 'entregado':
            badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            text = label || 'Finalizado';
            break;
        case 'pendiente':
        case 'planned':
        case 'planificado':
            badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
            text = label || 'Pendiente';
            break;
        case 'parado':
        case 'paused':
            badgeClass = 'bg-orange-50 text-orange-700 border-orange-200';
            text = label || 'Parado';
            break;
        case 'cancelado':
        case 'cancelled':
        case 'failed':
            badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
            text = label || 'Cancelado';
            break;
        case 'sin_actividad':
            badgeClass = 'bg-slate-100 text-slate-600 border-slate-200';
            text = label || 'Sin Actividad';
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
                className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 ${className}`}
            >
                Con Deuda {balance ? `($${Number(balance).toLocaleString('es-AR')})` : ''}
            </span>
        );
    }

    return (
        <span
            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 ${className}`}
        >
            Al Día
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
    let style = 'bg-emerald-50 text-emerald-700 border-emerald-200';

    if (m.includes('transf')) {
        label = 'Transferencia';
        style = 'bg-sky-50 text-sky-700 border-sky-200';
    } else if (m.includes('credit') || m.includes('cuenta') || m.includes('cte')) {
        label = 'Crédito / Cta Cte';
        style = 'bg-purple-50 text-purple-700 border-purple-200';
    }

    return (
        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${style} ${className}`}>
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
}

export function KpiCard({
    title,
    value,
    subtitle,
    icon: Icon,
    trend,
    variant = 'primary',
    className = '',
}: KpiCardProps) {
    const iconColors = {
        primary: 'bg-blue-50 text-blue-600 border-blue-100',
        accent: 'bg-orange-50 text-orange-600 border-orange-100',
        success: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        amber: 'bg-amber-50 text-amber-600 border-amber-100',
        slate: 'bg-slate-100 text-slate-700 border-slate-200',
    };

    return (
        <div
            className={`bg-white rounded-lg border border-slate-200 p-5 shadow-xs transition hover:border-slate-300 ${className}`}
        >
            <div className="flex items-center justify-between">
                <p className="text-xs font-medium uppercase tracking-wider text-slate-500">{title}</p>
                <div className={`p-2.5 rounded-lg border ${iconColors[variant]}`}>
                    <Icon className="w-5 h-5" />
                </div>
            </div>

            <div className="mt-3 flex items-baseline justify-between">
                <h3 className="text-2xl font-bold font-display text-slate-900">{value}</h3>
                {trend && (
                    <div
                        className={`flex items-center text-xs font-semibold ${
                            trend.neutral
                                ? 'text-slate-500'
                                : trend.isPositive
                                ? 'text-emerald-600'
                                : 'text-rose-600'
                        }`}
                    >
                        {trend.neutral ? (
                            <Minus className="w-3.5 h-3.5 mr-0.5" />
                        ) : trend.isPositive ? (
                            <TrendingUp className="w-3.5 h-3.5 mr-0.5" />
                        ) : (
                            <TrendingDown className="w-3.5 h-3.5 mr-0.5" />
                        )}
                        <span>{trend.value}</span>
                    </div>
                )}
            </div>

            {(subtitle || trend?.label) && (
                <p className="mt-1 text-xs text-slate-500">{subtitle || trend?.label}</p>
            )}
        </div>
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
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <div className="mb-1 flex items-center gap-1.5 text-xs text-slate-500">
                        {breadcrumbs.map((b, idx) => (
                            <React.Fragment key={idx}>
                                {idx > 0 && <span>/</span>}
                                {b.href ? (
                                    <a href={b.href} className="hover:text-blue-600 transition">
                                        {b.label}
                                    </a>
                                ) : (
                                    <span className="text-slate-700 font-medium">{b.label}</span>
                                )}
                            </React.Fragment>
                        ))}
                    </div>
                )}
                <h1 className="text-2xl font-bold font-display tracking-tight text-slate-900">{title}</h1>
                {description && <p className="text-sm text-slate-500 mt-0.5">{description}</p>}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
