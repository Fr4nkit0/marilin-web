import { LucideIcon, Minus, TrendingDown, TrendingUp } from 'lucide-react';

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
        primary: 'bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-300 border border-sky-100 dark:border-sky-500/30',
        accent: 'bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-300 border border-orange-100 dark:border-orange-500/30',
        success: 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-500/30',
        amber: 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-300 border border-amber-100 dark:border-amber-500/30',
        slate: 'bg-canvas-soft text-ink-secondary border border-hairline',
    };

    const Wrapper = onClick ? 'button' : 'div';

    return (
        <Wrapper
            {...(onClick ? { type: 'button' as const, onClick } : {})}
            className={`min-w-0 bg-canvas rounded-2xl border border-hairline/60 p-4 sm:p-5 shadow-sm transition-shadow hover:shadow-md ${
                onClick
                    ? 'w-full text-left cursor-pointer hover:border-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-600/30'
                    : ''
            } ${className}`}
        >
            {/* Two cards share a row on a phone, so the text steps down a size there. */}
            <div className="flex items-start justify-between gap-2">
                <p className="min-w-0 text-xs sm:text-sm font-medium text-ink-muted">{title}</p>
                <div className={`shrink-0 p-2 rounded-xl ${iconColors[variant]}`}>
                    <Icon className="w-4 h-4" strokeWidth={2} />
                </div>
            </div>

            <div className="mt-3 sm:mt-4 flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                <h3 className="min-w-0 break-words text-lg sm:text-2xl font-semibold text-ink tracking-tight">{value}</h3>
                {trend && (
                    <div
                        className={`flex items-center text-xs font-medium ${
                            trend.neutral
                                ? 'text-ink-muted bg-canvas-soft border border-hairline/60'
                                : trend.isPositive
                                ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/15 border border-emerald-200/60 dark:border-emerald-500/30'
                                : 'text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-500/15 border border-rose-200/60 dark:border-rose-500/30'
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
                <p className="mt-2 text-xs text-ink-subtle">{subtitle || trend?.label}</p>
            )}
            {onClick && <p className="mt-3 text-xs font-medium text-sky-600 dark:text-sky-300">Ver detalle →</p>}
        </Wrapper>
    );
}
