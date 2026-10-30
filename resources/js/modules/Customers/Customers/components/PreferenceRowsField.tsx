import { Plus, Trash2 } from 'lucide-react';
import Button from '@/ui/Button';
import { Field, Input, Select } from '@/ui/Field';
import { weekdayName } from '@/lib/format';
import { PreferenceInput } from '../types';

interface Props {
    /** Prefix of the controls' ids, so the create and edit forms do not clash. */
    idPrefix: string;
    rows: PreferenceInput[];
    onChange: (rows: PreferenceInput[]) => void;
    /** The form's errors; a row's are under `preferences.<index>.<field>`. */
    errors: Record<string, string | undefined>;
}

const DAYS = [1, 2, 3, 4, 5, 6, 0];

export const emptyPreference = (): PreferenceInput => ({ day_of_week: '1', time_window_start: '', time_window_end: '' });

/** The delivery preference rows a customer form adds: a day and an optional window each. */
export default function PreferenceRowsField({ idPrefix, rows, onChange, errors }: Props) {
    const change = (index: number, field: keyof PreferenceInput, value: string) =>
        onChange(rows.map((row, at) => (at === index ? { ...row, [field]: value } : row)));

    return (
        <div className="space-y-3">
            {rows.map((row, index) => {
                const error = (field: keyof PreferenceInput) => errors[`preferences.${index}.${field}`];

                return (
                    <div key={index} className="grid grid-cols-[1fr_1fr_1fr_auto] items-start gap-3">
                        <Field label="Día" error={error('day_of_week')}>
                            <Select
                                id={`${idPrefix}-preference-${index}-day`}
                                value={row.day_of_week}
                                invalid={Boolean(error('day_of_week'))}
                                onChange={(e) => change(index, 'day_of_week', e.target.value)}
                            >
                                {DAYS.map((day) => (
                                    <option key={day} value={day}>
                                        {weekdayName(day)}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Desde" error={error('time_window_start')}>
                            <Input
                                id={`${idPrefix}-preference-${index}-start`}
                                type="time"
                                value={row.time_window_start}
                                invalid={Boolean(error('time_window_start'))}
                                onChange={(e) => change(index, 'time_window_start', e.target.value)}
                            />
                        </Field>
                        <Field label="Hasta" error={error('time_window_end')}>
                            <Input
                                id={`${idPrefix}-preference-${index}-end`}
                                type="time"
                                value={row.time_window_end}
                                invalid={Boolean(error('time_window_end'))}
                                onChange={(e) => change(index, 'time_window_end', e.target.value)}
                            />
                        </Field>
                        <Button
                            variant="ghost"
                            className="mt-7"
                            aria-label={`Quitar ${weekdayName(Number(row.day_of_week))}`}
                            onClick={() => onChange(rows.filter((_, at) => at !== index))}
                        >
                            <Trash2 className="w-4 h-4" />
                        </Button>
                    </div>
                );
            })}
            <Button variant="secondary" size="sm" onClick={() => onChange([...rows, emptyPreference()])}>
                <Plus className="w-4 h-4" />
                Agregar día
            </Button>
        </div>
    );
}
