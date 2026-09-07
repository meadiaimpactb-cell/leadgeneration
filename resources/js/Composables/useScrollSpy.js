import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Which anchored section the visitor is currently reading.
 *
 * The landing page is one long document with five destinations in the header,
 * and a nav that never changes tells the visitor nothing about where they
 * are. This marks the current one, which is also what `aria-current` needs to
 * say for a screen reader to be told the same thing the sighted visitor is
 * shown.
 *
 * IntersectionObserver rather than a scroll listener: the browser reports
 * intersection off the main thread, so this costs nothing per frame, which is
 * the difference that matters on a page this tall (§15.1 caps INP at 200ms).
 * A scroll handler running on every frame over 27 sections is exactly the
 * "unnecessary re-render" the brief rules out.
 *
 * @param {import('vue').Ref<string[]>|string[]} ids  section ids, in page order
 */
export function useScrollSpy(ids) {
    const active = ref(null);

    let observer = null;

    /**
     * The visible section nearest the top of the viewport.
     *
     * Several sections are on screen at once on a desktop, so "is visible" is
     * not enough to pick one. The topmost visible one is the one whose
     * heading the visitor has most recently passed, which is what a reader
     * means by "where I am".
     */
    const visible = new Map();

    function pick() {
        let best = null;
        let bestTop = Infinity;

        for (const [id, rect] of visible) {
            if (rect.top < bestTop) {
                bestTop = rect.top;
                best = id;
            }
        }

        active.value = best;
    }

    onMounted(() => {
        const list = Array.isArray(ids) ? ids : (ids.value ?? []);

        // No observer means no highlight — the nav still navigates. Degrading
        // to "no section marked" is correct; guessing one is not.
        if (typeof window === 'undefined' || !('IntersectionObserver' in window)) {
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        visible.set(entry.target.id, entry.boundingClientRect);
                    } else {
                        visible.delete(entry.target.id);
                    }
                }

                pick();
            },
            {
                /*
                 * The header covers the top of the viewport, so a section is
                 * only "current" once it clears the header. The bottom margin
                 * keeps a section from claiming the highlight while it is
                 * still only a sliver at the foot of the screen.
                 */
                rootMargin: '-25% 0px -60% 0px',
                threshold: 0,
            }
        );

        for (const id of list) {
            const element = document.getElementById(id);

            if (element) {
                observer.observe(element);
            }
        }
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        observer = null;
        visible.clear();
    });

    return { active };
}
