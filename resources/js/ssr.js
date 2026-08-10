import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { i18n } from '@/plugins/i18n';

/**
 * Server-side rendering entry point (§7.2).
 *
 * This is what makes the site indexable: every page is delivered as complete
 * HTML rather than an empty shell hydrated later. SEO is a hard requirement
 * here, so if this process is not running, the site is not shippable.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,

        title: (title) => title,

        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

            return pages[`./Pages/${name}.vue`];
        },

        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(i18n);
        },
    })
);
