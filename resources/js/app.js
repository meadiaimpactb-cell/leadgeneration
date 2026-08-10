import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { i18n } from '@/plugins/i18n';

createInertiaApp({
    // The <title> is set per page by <Head> in PublicLayout, from the
    // server-built SEO block. It is never composed here.
    title: (title) => title,

    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },

    progress: {
        color: '#D7653B',
        showSpinner: false,
    },
});
