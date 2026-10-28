import { ButtonHTMLAttributes, ReactNode } from 'react';

interface DriverActionButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    icon?: ReactNode;
    variant?: 'primary' | 'danger' | 'success';
}

export default function DriverActionButton({
    className = '',
    disabled,
    children,
    icon,
    variant = 'primary',
    ...props
}: DriverActionButtonProps) {
    const baseClasses = "inline-flex items-center justify-center rounded-md border border-transparent px-4 py-3 text-base font-bold text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-md min-h-[52px] w-full gap-2";
    
    const variants = {
        primary: "bg-slate-900 hover:bg-slate-800 focus:ring-indigo-500 active:bg-slate-950",
        danger: "bg-red-600 hover:bg-red-700 focus:ring-red-500 active:bg-red-800",
        success: "bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 active:bg-emerald-800"
    };

    return (
        <button
            {...props}
            className={`${baseClasses} ${variants[variant]} ${className}`}
            disabled={disabled}
        >
            {icon && <span className="flex-shrink-0">{icon}</span>}
            {children}
        </button>
    );
}
