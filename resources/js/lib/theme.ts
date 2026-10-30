import { useEffect, useState } from 'react';

/** What the user picked; "system" follows the operating system's light or dark setting. */
export type ThemeChoice = 'system' | 'light' | 'dark';

export const THEME_STORAGE_KEY = 'marilin-theme';

const CHOICES: ThemeChoice[] = ['system', 'light', 'dark'];

const DARK_QUERY = '(prefers-color-scheme: dark)';

export function readChoice(): ThemeChoice {
    const stored = localStorage.getItem(THEME_STORAGE_KEY);

    return CHOICES.includes(stored as ThemeChoice) ? (stored as ThemeChoice) : 'system';
}

export function resolveTheme(choice: ThemeChoice, systemPrefersDark: boolean): 'light' | 'dark' {
    if (choice === 'system') {
        return systemPrefersDark ? 'dark' : 'light';
    }

    return choice;
}

/** Puts or takes off the `dark` class on <html>, which switches every color token. */
export function applyTheme(choice: ThemeChoice): void {
    const dark = resolveTheme(choice, window.matchMedia(DARK_QUERY).matches) === 'dark';
    document.documentElement.classList.toggle('dark', dark);
}

export function setChoice(choice: ThemeChoice): void {
    localStorage.setItem(THEME_STORAGE_KEY, choice);
    applyTheme(choice);
}

/** The user's theme choice, applied, and kept in step with the system while the choice is "system". */
export function useTheme() {
    const [choice, setState] = useState<ThemeChoice>(readChoice);

    useEffect(() => {
        applyTheme(choice);

        if (choice !== 'system') {
            return;
        }

        const media = window.matchMedia(DARK_QUERY);
        const follow = (event: { matches: boolean }) => document.documentElement.classList.toggle('dark', event.matches);
        media.addEventListener('change', follow);

        return () => media.removeEventListener('change', follow);
    }, [choice]);

    return {
        choice,
        setChoice: (next: ThemeChoice) => {
            setChoice(next);
            setState(next);
        },
    };
}
