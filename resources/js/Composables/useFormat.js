import { usePage } from '@inertiajs/vue3';

/**
 * Number and date formatting for the whole platform.
 *
 * Two decisions live here, and nowhere else:
 *
 * 1. **Digits are always Latin (0–9), in both languages.** The default
 *    `ar-SA` formatter renders Arabic-Indic digits (١٨٬٥٠٠), which reads as a
 *    different number to anyone scanning a dashboard, cannot be copied into a
 *    spreadsheet or a CRM, and has no tabular variant in the body face — so
 *    columns of figures fail to align despite `font-variant-numeric`. §10.3
 *    already puts numerals in IBM Plex Sans with tabular-nums, which assumes
 *    Latin digits.
 *
 * 2. **Dates are always Gregorian.** `ar-SA` defaults to the Hijri calendar in
 *    most browsers. A lead timestamp shown as a Hijri date would not match the
 *    same lead in the CRM, in GA4, or in an exported CSV.
 *
 * Arabic month names are kept — it is an Arabic interface. Only the numerals
 * and the calendar are pinned.
 */

/** Arabic locale, Gregorian calendar, Latin numerals. */
const AR = 'ar-SA-u-ca-gregory-nu-latn';
const EN = 'en-GB';

export function useFormat() {
    const page = usePage();

    const tag = () => (page.props.locale === 'en' ? EN : AR);

    /** 18500 → "18,500" in both languages. */
    function number(value) {
        if (value === null || value === undefined || value === '') return '—';

        const n = Number(value);

        return Number.isFinite(n) ? new Intl.NumberFormat('en-US').format(n) : '—';
    }

    /** ISO string → "5 أغسطس 2026" / "5 Aug 2026". */
    function date(iso, options = {}) {
        if (!iso) return '—';

        return new Intl.DateTimeFormat(tag(), {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            ...options,
        }).format(new Date(iso));
    }

    /** ISO string → the same, plus a 24-hour time. */
    function dateTime(iso) {
        if (!iso) return '—';

        return new Intl.DateTimeFormat(tag(), {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).format(new Date(iso));
    }

    /** Short axis label for charts: "5 أغسطس" / "5 Aug". */
    function dayMonth(iso) {
        return date(iso, { year: undefined, month: 'short', day: 'numeric' });
    }

    return { number, date, dateTime, dayMonth };
}
