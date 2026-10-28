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
        primary: 'bg-indigo-50 text-indigo-600 border border-indigo-100',
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
                    ? 'w-full text-left cursor-pointer hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-600/30'
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
            {onClick && <p className="mt-3 text-xs font-medium text-indigo-600">Ver detalle →</p>}
        </Wrapper>
    );
}
