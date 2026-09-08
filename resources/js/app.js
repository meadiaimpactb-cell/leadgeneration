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
        /*
         * The accent, read from the theme rather than written down.
         *
         * The client can change the four identity colours from the panel now,
         * and this bar is drawn by Inertia into an element of its own with an
         * inline style — so it is the one piece of the interface a stylesheet
         * cannot reach. A literal here meant the loading bar stayed the old
         * orange after a rebrand had changed everything around it.
         *
         * This entry point is the browser's; SSR has its own (ssr.js), and it
         * has no progress bar. The stylesheet and the palette's own <style>
         * block are both in <head> before a module script runs, so the token
         * is resolved by the time this is read. The literal is the fallback
         * for the one case that leaves it empty: no stylesheet at all.
         */
        color:
            getComputedStyle(document.documentElement)
                .getPropertyValue('--orange-500')
                .trim() || '#D7653B',
        showSpinner: false,
    },
});
