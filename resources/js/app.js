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
