import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // A page's name is its path under modules/ (ADR 0003): 'Street/Sales/Index' -> modules/Street/Sales/Index.tsx.
    // Pages/ is still read while the pages move to their modules.
    resolve: (name) => {
        const modules = import.meta.glob(['./modules/**/*.tsx', '!./modules/**/components/**', '!./modules/**/*.test.tsx']);
        const path = `./modules/${name}.tsx`;

        return path in modules
            ? resolvePageComponent(path, modules)
            : resolvePageComponent(`./Pages/${name}.tsx`, import.meta.glob('./Pages/**/*.tsx'));
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
