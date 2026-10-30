import { cloneElement, forwardRef, isValidElement, useId, type InputHTMLAttributes, type LabelHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';

const CONTROL =
    'w-full h-10 px-3 rounded-md border border-hairline bg-canvas dark:bg-canvas-soft text-sm text-ink placeholder-ink-subtle shadow-sm ' +
    'focus:outline-none focus:ring-2 focus:ring-sky-600/20 focus:border-sky-600 disabled:bg-canvas-soft disabled:text-ink-muted';

const INVALID = 'border-rose-400 focus:ring-rose-600/20 focus:border-rose-600';

interface FieldProps {
    label: ReactNode;
    /** The control's id; when left out, the control's own id or a generated one ties the label to it. */
    htmlFor?: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
}

/** A labelled form control with its validation error under it. */
export function Field({ label, htmlFor, error, hint, children }: FieldProps) {
    const generatedId = useId();
    const control = isValidElement<{ id?: string }>(children) ? children : null;
    const id = htmlFor ?? control?.props.id ?? generatedId;

    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            {control && !control.props.id ? cloneElement(control, { id }) : children}
            {hint && !error ? <p className="text-xs text-ink-muted">{hint}</p> : null}
            <FieldError message={error} />
        </div>
    );
}

type Invalid = { invalid?: boolean };

export function Label({ value, className = '', children, ...rest }: LabelHTMLAttributes<HTMLLabelElement> & { value?: ReactNode }) {
    return (
        <label className={`block text-sm font-medium text-ink-secondary ${className}`.trim()} {...rest}>
            {value ?? children}
        </label>
    );
}

/** A validation message under a control; nothing when there is no message. */
export function FieldError({ message, className = '' }: { message?: string; className?: string }) {
    return message ? <p className={`text-xs text-rose-600 dark:text-rose-300 ${className}`.trim()}>{message}</p> : null;
}

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & Invalid>(function Input(
    { invalid, className = '', ...rest },
    ref,
) {
    return <input ref={ref} aria-invalid={invalid || undefined} className={`${CONTROL} ${invalid ? INVALID : ''} ${className}`.trim()} {...rest} />;
});

export function Checkbox({ className = '', ...rest }: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            type="checkbox"
            className={`h-4 w-4 rounded border-hairline-strong text-sky-600 dark:text-sky-300 focus:ring-sky-600 ${className}`.trim()}
            {...rest}
        />
    );
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
