import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Scroll reveal (§10.7): opacity 0→1 + translateY 16px→0, once only,
 * with a 60ms stagger for items inside a grid.
 *
 * Respects prefers-reduced-motion by never arming the observer — the element
 * simply starts in its final state, which the .reveal CSS handles.
 */
export function useReveal(options = {}) {
    const { stagger = 60, threshold = 0.15, rootMargin = '0px 0px -10% 0px' } = options;

    const root = ref(null);
    let observer = null;

    function prefersReducedMotion() {
        return (
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        );
    }

    onMounted(() => {
        if (root.value === null) return;

        const targets = root.value.matches?.('.reveal')
            ? [root.value]
            : Array.from(root.value.querySelectorAll('.reveal'));

        if (targets.length === 0) return;

        if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
            targets.forEach((el) => el.classList.add('is-revealed'));
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;

                    // Stagger is relative to the item's position in its own
                    // group, so a grid cascades but sections do not queue
                    // behind each other.
                    const group = entry.target.parentElement;
                    const index = group
                        ? Array.from(group.querySelectorAll(':scope > .reveal')).indexOf(entry.target)
                        : 0;

                    entry.target.style.setProperty(
                        '--reveal-delay',
                        `${Math.max(index, 0) * stagger}ms`
                    );
                    entry.target.classList.add('is-revealed');

                    // Once only (§10.7).
                    observer.unobserve(entry.target);
                });
            },
            { threshold, rootMargin }
        );

        targets.forEach((el) => observer.observe(el));
    });

    onUnmounted(() => observer?.disconnect());

    return { root };
}
