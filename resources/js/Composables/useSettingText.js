import { usePage } from '@inertiajs/vue3';

/**
 * Reading per-language text out of a section's `settings`.
 *
 * A section's translated columns are per-locale rows; `settings` is a single
 * JSON column that is not. So a repeatable list inside it — process steps,
 * audience columns, the two forms of a CTA heading — carries both languages on
 * each item, `title` and `title_en`.
 *
 * There is deliberately no fallback to the Arabic key. §12 is explicit that an
 * English visitor is never served Arabic, so a missing English string yields
 * nothing and the line simply does not render — a shorter page rather than a
 * half-translated one.
 *
 * The media library makes this unnecessary for images. For repeatable text it
 * is the shape the section builder already stores.
 */
export function useSettingText() {
    const page = usePage();

    function text(source, field) {
        if (!source) return null;

        return page.props.locale === 'en'
            ? (source[`${field}_en`] ?? null)
            : (source[field] ?? null);
    }

    return { text };
}
