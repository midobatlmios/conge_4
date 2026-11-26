import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { applyLocale, Locale } from './lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Gestion des Congés';

// Installable app (PWA). Only in the built app: in `npm run dev` it would get in the way of hot reload.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Not installable on this browser; the site works the same.
        });
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => {
                    // Language and direction (Arabic: right to left) set before the page renders,
                    // so every t() of the page uses the new language right after a switch.
                    applyLocale(pageProps.locale as Locale);
                    return <Component key={key} {...pageProps} />;
                }}
            </App>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
