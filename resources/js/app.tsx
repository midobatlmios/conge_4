import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { applyLocale, Locale } from './lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Gestion des Congés';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);
        // Language and direction (Arabic: right to left), kept up to date on every visit.
        applyLocale(props.initialPage.props.locale as Locale);
        router.on('navigate', (event) => applyLocale(event.detail.page.props.locale as Locale));

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
