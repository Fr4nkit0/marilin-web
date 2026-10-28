import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

const CONTROL =
    'w-full h-10 px-3 rounded-md border border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 shadow-sm ' +
    'focus:outline-none focus:ring-2 focus:ring-indigo-600/20 focus:border-indigo-600 disabled:bg-slate-50 disabled:text-slate-500';

const INVALID = 'border-rose-400 focus:ring-rose-600/20 focus:border-rose-600';

interface FieldProps {
    label: ReactNode;
    htmlFor: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
}

/** A labelled form control with its validation error under it. */
export function Field({ label, htmlFor, error, hint, children }: FieldProps) {
    return (
        <div className="space-y-1.5">
            <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {hint && !error ? <p className="text-xs text-slate-500">{hint}</p> : null}
            {error ? <p className="text-xs text-rose-600">{error}</p> : null}
        </div>
    );
}

type Invalid = { invalid?: boolean };

export function Input({ invalid, className = '', ...rest }: InputHTMLAttributes<HTMLInputElement> & Invalid) {
    return <input aria-invalid={invalid || undefined} className={`${CONTROL} ${invalid ? INVALID : ''} ${className}`.trim()} {...rest} />;
}

export function Select({ invalid, className = '', ...rest }: SelectHTMLAttributes<HTMLSelectElement> & Invalid) {
    return <select aria-invalid={invalid || undefined} className={`${CONTROL} pr-8 ${invalid ? INVALID : ''} ${className}`.trim()} {...rest} />;
}

export function Textarea({ invalid, className = '', ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement> & Invalid) {
    return (
        <textarea
            aria-invalid={invalid || undefined}
            className={`${CONTROL} h-auto min-h-20 py-2 ${invalid ? INVALID : ''} ${className}`.trim()}
            {...rest}
        />
    );
}
