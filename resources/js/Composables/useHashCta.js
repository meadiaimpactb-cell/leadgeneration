/**
 * A CTA whose target is on the page it is already on.
 *
 * Written once and used by both heroes. `Hero` (home) had it inline first;
 * `SegmentHero` (the four audience-segment pages) needed exactly the same
 * behaviour the day their buttons stopped leaving for /contact, and two copies
 * of a scroll-and-focus routine drift the moment either is touched.
 *
 * What it adds over a bare `<a href="#lead">`:
 *
 * The anchor alone already scrolls, and `scroll-margin` already clears the
 * fixed header — but it leaves the visitor looking at a form they still have
 * to click into. Focusing the first field is the difference between arriving
 * at the form and being in it.
 *
 * Only for in-page targets: anything else is left to navigate normally. And
 * only when the target exists, so a CTA pointing at a section the client has
 * removed from the panel still behaves like an ordinary link instead of
 * swallowing the click.
 */
export function useHashCta() {
    /**
     * @param  {MouseEvent}  event
     * @param  {string|null} href  the button's own URL, as the panel stores it
     */
    function onHashCta(event, href) {
        const target = href ?? '';

        if (!target.startsWith('#') || typeof document === 'undefined') {
            return;
        }

        const element = document.querySelector(target);

        if (!element) {
            return;
        }

        event.preventDefault();

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        element.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });

        /*
         * `focus({preventScroll: true})` — focusing normally yanks the page to
         * the field and cancels the smooth scroll that is still running, which
         * reads as a jump. The scroll above owns the movement; the focus only
         * moves the caret.
         */
        element.querySelector('input, textarea, select')?.focus({ preventScroll: true });
    }

    return { onHashCta };
}
