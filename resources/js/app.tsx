import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Marilin';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // A page's name is its path under modules/ (ADR 0003): 'Street/Sales/Index' -> modules/Street/Sales/Index.tsx.
    resolve: (name) =>
        resolvePageComponent(
            `./modules/${name}.tsx`,
            import.meta.glob(['./modules/**/*.tsx', '!./modules/**/components/**', '!./modules/**/*.test.tsx']),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
