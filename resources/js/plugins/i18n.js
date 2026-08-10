import { usePage } from '@inertiajs/vue3';

/**
 * UI string lookup for Vue components.
 *
 * Mirrors Laravel's __() so a string can move between a Blade template and a
 * Vue component without changing key. Strings come from resources/lang and
 * are shared per request by HandleInertiaRequests.
 *
 * §22.5 is absolute: never write a literal user-facing string in a template.
 * Site *content* does not come through here — that lives in the translation
 * tables and arrives as page props.
 */
export function translate(key, replacements = {}) {
    const messages = usePage().props.translations ?? {};
    let line = messages[key];

    if (line === undefined) {
        // Surfacing the key beats rendering an empty element: a missing
        // string becomes visible in review instead of silently disappearing.
        if (import.meta.env.DEV) {
            console.warn(`[i18n] missing translation: ${key}`);
        }

        return key;
    }

    for (const [token, value] of Object.entries(replacements)) {
        line = line.replace(new RegExp(`:${token}\\b`, 'g'), String(value));
    }

    return line;
}

export const i18n = {
    install(app) {
        app.config.globalProperties.$t = translate;
        app.provide('t', translate);
    },
};
