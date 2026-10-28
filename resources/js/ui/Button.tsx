import type { ButtonHTMLAttributes } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
export type ButtonSize = 'sm' | 'md';

const VARIANT: Record<ButtonVariant, string> = {
    primary: 'bg-sky-600 text-white hover:bg-sky-700 shadow-sm',
    secondary: 'bg-canvas text-ink-secondary border border-hairline hover:bg-canvas-soft shadow-sm',
    danger: 'bg-rose-600 text-white hover:bg-rose-700 shadow-sm',
    ghost: 'text-ink-secondary hover:text-ink hover:bg-surface-1',
};

const SIZE: Record<ButtonSize, string> = {
    sm: 'h-8 px-3 text-xs',
    md: 'h-10 px-4 text-sm',
};

/** The classes of a button, also for links that look like one (`<Link className={buttonClass('primary')}>`). */
export function buttonClass(variant: ButtonVariant = 'primary', size: ButtonSize = 'md'): string {
    return [
        'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors cursor-pointer',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 focus-visible:ring-offset-2',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        VARIANT[variant],
        SIZE[size],
    ].join(' ');
}

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    /** The form is being sent (Inertia's `processing`): the button cannot be pressed again. */
    processing?: boolean;
}

export default function Button({ variant = 'primary', size = 'md', processing = false, type = 'button', className = '', disabled, ...rest }: Props) {
    return (
        <button
            type={type}
            disabled={disabled || processing}
            aria-busy={processing || undefined}
            className={`${buttonClass(variant, size)} ${className}`.trim()}
            {...rest}
        />
    );
}
