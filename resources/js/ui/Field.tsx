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

/**
 * A labelled form control with its validation error under it. The label alone names the control;
 * the hint, or the error in its place, is the control's description.
 */
export function Field({ label, htmlFor, error, hint, children }: FieldProps) {
    const generatedId = useId();
    const descriptionId = `${generatedId}-description`;
    const control = isValidElement<{ id?: string; 'aria-describedby'?: string }>(children) ? children : null;
    const id = htmlFor ?? control?.props.id ?? generatedId;
    const described = Boolean(error) || Boolean(hint);
    const describedBy = [control?.props['aria-describedby'], described ? descriptionId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className="space-y-1.5">
            <Label htmlFor={id}>{label}</Label>
            {control ? cloneElement(control, { id: control.props.id ?? id, 'aria-describedby': describedBy }) : children}
            {hint && !error ? (
                <p id={descriptionId} className="text-xs text-ink-muted">
                    {hint}
                </p>
            ) : null}
            <FieldError id={descriptionId} message={error} />
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
export function FieldError({ message, className = '', id }: { message?: string; className?: string; id?: string }) {
    return message ? (
        <p id={id} className={`text-xs text-rose-600 dark:text-rose-300 ${className}`.trim()}>
            {message}
        </p>
    ) : null;
}

type InputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'prefix'> &
    Invalid & {
        /** A short mark inside the control before the value, such as "$"; the input keeps the id, so its label still names it. */
        prefix?: ReactNode;
    };

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input({ invalid, prefix, className = '', ...rest }, ref) {
    const input = (
        <input
            ref={ref}
            aria-invalid={invalid || undefined}
            className={`${CONTROL} ${invalid ? INVALID : ''} ${prefix ? 'pl-7' : ''} ${className}`.trim()}
            {...rest}
        />
    );

    if (!prefix) {
        return input;
    }

    return (
        <div className="relative">
            <span aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-ink-muted">
                {prefix}
            </span>
            {input}
        </div>
    );
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
