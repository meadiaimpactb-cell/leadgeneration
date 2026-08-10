import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Impact counters (§10.7): count up once on entry, over 900ms.
 *
 * Starts at the final value rather than zero, so a visitor with reduced
 * motion — or with JavaScript still loading — sees the real number, and so
 * the server-rendered HTML already contains it for crawlers.
 */
export function useCountUp(target, duration = 900) {
    const el = ref(null);
    const display = ref(target);
    let observer = null;
    let frame = null;

    function prefersReducedMotion() {
        return (
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        );
    }

    function run() {
        const from = 0;
        const start = performance.now();
        const isInteger = Number.isInteger(target);

        function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            // Same easing curve as every other motion on the site (§10.7).
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = from + (target - from) * eased;

            display.value = isInteger ? Math.round(value) : Math.round(value * 10) / 10;

            if (progress < 1) {
                frame = requestAnimationFrame(step);
            }
        }

        frame = requestAnimationFrame(step);
    }

    onMounted(() => {
        if (el.value === null) return;
        if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;

                    display.value = 0;
                    run();
                    observer.unobserve(entry.target);
                });
            },
            { threshold: 0.4 }
        );

        observer.observe(el.value);
    });

    onUnmounted(() => {
        observer?.disconnect();
        if (frame !== null) cancelAnimationFrame(frame);
    });

    return { el, display };
}
