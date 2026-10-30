import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
// A semantic color is a CSS variable in RGB channels, so `bg-canvas/80` keeps working.
const token = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;

export default {
    // Dark mode is a `dark` class on <html>, set by lib/theme from the user's choice or the system.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                brand: ['"Outfit"', '"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Neutral roles from DESIGN.md; each has a light and a dark value in resources/css/app.css.
                canvas: { DEFAULT: token('canvas'), soft: token('canvas-soft') },
                surface: { 1: token('surface-1'), 2: token('surface-2'), 3: token('surface-3') },
                ink: { DEFAULT: token('ink'), secondary: token('ink-secondary'), muted: token('ink-muted'), subtle: token('ink-subtle') },
                hairline: { DEFAULT: token('hairline'), strong: token('hairline-strong') },
                brand: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb', // Stitch Primary
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
                accent: {
                    50: '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#f97316',
                    600: '#ea580c', // Stitch Secondary
                    700: '#c2410c',
                },
            },
        },
    },

    plugins: [forms],
};
