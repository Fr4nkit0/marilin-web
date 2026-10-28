import { Monitor, Moon, Sun, type LucideIcon } from 'lucide-react';
import { useTheme, type ThemeChoice } from '@/lib/theme';

const OPTIONS: { value: ThemeChoice; label: string; icon: LucideIcon }[] = [
    { value: 'system', label: 'Sistema', icon: Monitor },
    { value: 'light', label: 'Claro', icon: Sun },
    { value: 'dark', label: 'Oscuro', icon: Moon },
];

/** Picks the theme: the system's, or always light, or always dark. */
export default function ThemeSwitch() {
    const { choice, setChoice } = useTheme();

    return (
        <div className="flex items-center gap-1 rounded-md bg-surface-1 p-1" role="group" aria-label="Tema">
            {OPTIONS.map(({ value, label, icon: Icon }) => (
                <button
                    key={value}
                    type="button"
                    aria-label={label}
                    aria-pressed={choice === value}
                    title={label}
                    onClick={() => setChoice(value)}
                    className={`flex-1 inline-flex items-center justify-center h-7 rounded transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-600 ${
                        choice === value ? 'bg-canvas text-ink shadow-sm' : 'text-ink-muted hover:text-ink'
                    }`}
                >
                    <Icon className="w-4 h-4" strokeWidth={2} />
                </button>
            ))}
        </div>
    );
}
