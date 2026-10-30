import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { THEME_STORAGE_KEY } from '@/lib/theme';
import ThemeSwitch from './ThemeSwitch';

beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    vi.stubGlobal('matchMedia', () => ({ matches: false, addEventListener: () => {}, removeEventListener: () => {} }));
});

describe('ThemeSwitch', () => {
    it('offers system, light and dark, with the current one pressed', () => {
        render(<ThemeSwitch />);

        expect(screen.getByRole('button', { name: 'Sistema' }).getAttribute('aria-pressed')).toBe('true');
        expect(screen.getByRole('button', { name: 'Claro' }).getAttribute('aria-pressed')).toBe('false');
        expect(screen.getByRole('button', { name: 'Oscuro' }).getAttribute('aria-pressed')).toBe('false');
    });

    it('switches to dark and remembers it', () => {
        render(<ThemeSwitch />);

        fireEvent.click(screen.getByRole('button', { name: 'Oscuro' }));

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('dark');
        expect(screen.getByRole('button', { name: 'Oscuro' }).getAttribute('aria-pressed')).toBe('true');
    });
});
