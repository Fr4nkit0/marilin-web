import React from 'react';
import { Minus, Plus } from 'lucide-react';
import { Field, FieldError, Input } from '@/ui/Field';

interface Props {
    balance: string;
    /** Count per container type id, as typed. */
    containers: Record<string, string>;
    containerTypes: { id: number; description: string }[];
    onBalanceChange: (value: string) => void;
    onContainersChange: (containers: Record<string, string>) => void;
    errors: Record<string, string>;
}

// The browser's own number arrows are tiny and theme-blind; the steppers below replace them.
const NO_SPINNER = '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';

const stepButton =
    'flex h-11 w-11 sm:h-10 sm:w-10 shrink-0 items-center justify-center text-ink-secondary transition-colors hover:bg-surface-1 hover:text-ink disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sky-600';

/**
 * What a customer who existed before the system brings along: the money they already owe and the containers
 * already at their place. Money and units are kept apart, and each container type gets its own row with a
 * stepper, so any number of types and any name length fit.
 */
export default function InitialConditionsFields({ balance, containers, containerTypes, onBalanceChange, onContainersChange, errors }: Props) {
    const count = (typeId: number) => Number.parseInt(containers[typeId] ?? '0', 10) || 0;
    const setCount = (typeId: number, value: string) => onContainersChange({ ...containers, [typeId]: value });

    return (
        <div className="space-y-5">
            <div className="sm:w-1/2">
                <Field label="Deuda inicial" htmlFor="create-initial-balance" error={errors.initial_balance} hint="Lo que ya debía antes de cargarlo.">
                    <Input
                        id="create-initial-balance"
                        type="number"
                        inputMode="decimal"
                        step="0.01"
                        min="0"
                        prefix="$"
                        value={balance}
                        invalid={Boolean(errors.initial_balance)}
                        onChange={(e) => onBalanceChange(e.target.value)}
                        className={`tabular-nums ${NO_SPINNER}`}
                    />
                </Field>
            </div>

            {containerTypes.length > 0 && (
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium text-ink-secondary">Envases en su casa</legend>
                    <FieldError message={errors.initial_containers} />
                    <ul className="divide-y divide-hairline rounded-lg border border-hairline">
                        {containerTypes.map((type) => {
                            const id = `initial-containers-${type.id}`;
                            const error = errors[`initial_containers.${type.id}`];
                            const value = count(type.id);

                            return (
                                <li key={type.id} className="px-3 py-2.5">
                                    <div className="flex items-center justify-between gap-4">
                                        <label htmlFor={id} className="text-sm text-ink">
                                            {type.description}
                                        </label>
                                        <div className="flex shrink-0 items-center overflow-hidden rounded-md border border-hairline bg-canvas dark:bg-canvas-soft">
                                            <button
                                                type="button"
                                                onClick={() => setCount(type.id, String(Math.max(0, value - 1)))}
                                                disabled={value <= 0}
                                                aria-label={`Restar un ${type.description}`}
                                                className={stepButton}
                                            >
                                                <Minus className="w-4 h-4" />
                                            </button>
                                            <input
                                                id={id}
                                                type="number"
                                                inputMode="numeric"
                                                min="0"
                                                step="1"
                                                value={containers[type.id] ?? '0'}
                                                aria-invalid={Boolean(error) || undefined}
                                                onChange={(e) => setCount(type.id, e.target.value)}
                                                className={`h-11 sm:h-10 w-14 border-x border-y-0 border-hairline bg-transparent p-0 text-center text-sm font-medium text-ink tabular-nums focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-600 ${NO_SPINNER}`}
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setCount(type.id, String(value + 1))}
                                                aria-label={`Sumar un ${type.description}`}
                                                className={stepButton}
                                            >
                                                <Plus className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <FieldError message={error} className="mt-1 text-right" />
                                </li>
                            );
                        })}
                    </ul>
                </fieldset>
            )}
        </div>
    );
}
