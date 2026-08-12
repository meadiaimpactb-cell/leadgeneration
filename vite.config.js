import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        // Per-page code splitting keeps initial JS inside the
        // ≤180KB gzipped budget (§15.1).
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        /*
                         * Left to its own chunk on purpose.
                         *
                         * intl-tel-input with libphonenumber's metadata is
                         * ~80KB gzipped. PhoneField imports it dynamically so
                         * only a page with a form pays for it — but naming it
                         * `vendor` here would pull it back into the shared
                         * bundle and undo that, putting every country's
                         * numbering rules on the front page. §15.1 caps
                         * initial JS at 180KB gzipped, and this alone was
                         * half of it.
                         */
                        if (id.includes('intl-tel-input')) {
                            return undefined;
                        }

                        if (id.includes('/vue') || id.includes('@inertiajs')) {
                            return 'vendor-core';
                        }
                        return 'vendor';
                    }
                },
            },
        },
    },
});
