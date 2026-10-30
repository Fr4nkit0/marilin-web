import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTheme, readChoice, resolveTheme, setChoice, THEME_STORAGE_KEY, useTheme } from './theme';

let systemDark = false;
let listeners: Array<(event: { matches: boolean }) => void> = [];

beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    systemDark = false;
    listeners = [];
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: query.includes('dark') ? systemDark : false,
        addEventListener: (_: string, listener: (event: { matches: boolean }) => void) => listeners.push(listener),
        removeEventListener: (_: string, listener: (event: { matches: boolean }) => void) => {
            listeners = listeners.filter((l) => l !== listener);
        },
    }));
});

const isDark = () => document.documentElement.classList.contains('dark');

describe('theme', () => {
    it('follows the system until the user picks one', () => {
        expect(readChoice()).toBe('system');
        expect(resolveTheme('system', true)).toBe('dark');
        expect(resolveTheme('system', false)).toBe('light');
    });

    it('a forced choice wins over the system', () => {
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('dark', false)).toBe('dark');
    });

    it('puts the dark class on the page and takes it off', () => {
        systemDark = true;
        applyTheme('system');
        expect(isDark()).toBe(true);

        applyTheme('light');
        expect(isDark()).toBe(false);
    });

    it('remembers the choice in the browser', () => {
        setChoice('dark');

        expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark');
        expect(readChoice()).toBe('dark');
        expect(isDark()).toBe(true);
    });

    it('ignores a stored value it does not know', () => {
        localStorage.setItem(THEME_STORAGE_KEY, 'sepia');

        expect(readChoice()).toBe('system');
    });

    it('useTheme changes the choice and follows the system while on system', () => {
        const { result } = renderHook(() => useTheme());
        expect(result.current.choice).toBe('system');
        expect(isDark()).toBe(false);

        act(() => listeners.forEach((listener) => listener({ matches: true })));
        expect(isDark()).toBe(true);

        act(() => result.current.setChoice('light'));
        expect(result.current.choice).toBe('light');
        expect(isDark()).toBe(false);

        act(() => listeners.forEach((listener) => listener({ matches: true })));
        expect(isDark()).toBe(false);
    });
});
