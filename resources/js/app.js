import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const appName = 'ProCTAD — CSC Regional Office VIII';

createInertiaApp({
    title: (title) => (title ? `${title} — ProCTAD` : appName),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#EC1C2D',
    },
});

/**
 * Register the service worker for exam-day offline resilience (see public/sw.js).
 * Production only — in dev a caching worker fights Vite's HMR. Failure here is
 * non-fatal: the app runs fine without it, just without the offline shell.
 */
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
