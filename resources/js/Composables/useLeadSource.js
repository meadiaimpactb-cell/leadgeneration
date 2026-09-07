import { inject, provide, ref } from 'vue';
import { useHashCta } from '@/Composables/useHashCta';

/**
 * Which segment the visitor pressed a button from — carried to the one form.
 *
 * The landing page has exactly one contact form (§6.1, and the management
 * decision of 7 September 2026 that made it the site's only form). Every call
 * to action on the page scrolls to that same form. The only thing that
 * differs between them is WHO is asking, and that has to reach the sales team
 * without asking the visitor to classify themselves in a third field.
 *
 * So the button records it instead. The value lands in the lead's
 * `sector_hint` column — which already existed for exactly this and is
 * already validated in StoreLeadRequest — and the form keeps the two visible
 * controls the brief fixes it at.
 *
 * `provide`/`inject` rather than a module-level ref on purpose: under SSR a
 * module-level value is shared by every request the node process renders, so
 * one visitor's segment could be served to the next. A provided ref lives and
 * dies with the component tree that owns it.
 */
const LEAD_SOURCE = Symbol('leadSource');

/** Called once, by the page that owns the form. */
export function provideLeadSource() {
    const source = ref(null);
    const { onHashCta } = useHashCta();

    /**
     * Send the visitor to the form, remembering which button they pressed.
     *
     * The scroll and the focus are `useHashCta`'s — the same routine every
     * other in-page CTA on the site already uses, so there is one definition
     * of "arrive at the form" rather than two that drift.
     *
     * @param {MouseEvent} event
     * @param {string|null} value  government | partner | artisan | null
     */
    function goToForm(event, value = null) {
        source.value = value ?? null;
        onHashCta(event, '#contact');
    }

    const api = { source, goToForm };

    provide(LEAD_SOURCE, api);

    return api;
}

/**
 * Read the current segment, and the helper that sets it.
 *
 * Falls back to an inert pair so a section component rendered outside the
 * landing page — in the admin panel's preview, say — degrades to an ordinary
 * anchor instead of throwing.
 */
export function useLeadSource() {
    return inject(LEAD_SOURCE, { source: ref(null), goToForm: () => {} });
}
