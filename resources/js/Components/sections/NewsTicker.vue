<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/NewsTicker — the running strip above the header.
 *
 * The headlines are the section's own body text, one per line. That is a
 * deliberate choice over a `news` table: the body field is already
 * per-language (§12), already editable in the section builder, and already
 * respects the "untranslated records do not fall back" rule — so an Arabic
 * headline can never leak onto the English site. A new table would have
 * needed a migration, a model, an admin screen and its own translation
 * plumbing to arrive at the same place.
 *
 * Nothing renders unless the client has written at least one line (§22.1).
 */
const props = defineProps({
    /** The badge on the leading edge. No heading, no badge. */
    heading: { type: String, default: null },
    /** One headline per line. */
    body: { type: String, default: null },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
});

const { t } = useTranslation();

const items = computed(() =>
    (props.body ?? '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
);
</script>

<template>
    <div
        v-if="items.length"
        class="news"
        role="region"
        :aria-label="heading ?? t('common.news')"
    >
        <p v-if="heading" class="news__badge">
            <svg
                class="news__icon"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                aria-hidden="true"
            >
                <path d="M4 9h4l7-4v14l-7-4H4z" />
                <path d="M18 9c1.4 1.4 1.4 4.2 0 6" />
            </svg>
            {{ heading }}
        </p>

        <div class="news__viewport marquee">
            <div class="marquee__track news__track">
                <!--
                    The same list twice. The animation travels exactly -50%,
                    at which point copy two occupies copy one's starting
                    position, so the restart is invisible. Only the first copy
                    is exposed to assistive technology.
                -->
                <ul class="news__copy">
                    <li v-for="(item, i) in items" :key="`a-${i}`" class="news__item">
                        {{ item }}
                        <span class="news__sep" aria-hidden="true">&#9670;</span>
                    </li>
                </ul>
                <ul class="news__copy" aria-hidden="true">
                    <li v-for="(item, i) in items" :key="`b-${i}`" class="news__item">
                        {{ item }}
                        <span class="news__sep">&#9670;</span>
                    </li>
                </ul>
            </div>
        </div>

        <Link v-if="ctaLabel" :href="ctaUrl ?? '#'" class="news__more">
            {{ ctaLabel }}
            <span class="arrow" aria-hidden="true">&#8594;</span>
        </Link>
    </div>
</template>

<style scoped>
.news {
    position: relative;
    display: flex;
    align-items: stretch;
    min-block-size: var(--news-h);
    background: var(--navy-950);
    border-block-end: 1px solid var(--hairline-gold);
    overflow: hidden;
}

.news__badge {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    flex-shrink: 0;
    padding-inline: var(--s-5);
    background: var(--action-600);
    color: #fff;
    font-size: 0.78125rem;
    font-weight: 600;
    /* Without this the badge wraps to two lines the moment the label is
       longer than one word, and takes the whole strip's height with it. */
    white-space: nowrap;
    z-index: 2;
}

.news__icon {
    flex-shrink: 0;
}

.news__viewport {
    flex: 1;
    min-inline-size: 0;
    --marquee-dur: var(--dur-marquee-news);
    --marquee-fade: 4%;
}

/*
 * The track runs left-to-right regardless of document direction. Arabic
 * headlines still set RTL inside each item — only the travel is pinned, so
 * switching language does not reverse the direction of motion.
 */
.news__track {
    direction: ltr;
    block-size: 100%;
}

.news__copy {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    /*
     * Each copy is at least a viewport wide. The loop only looks seamless
     * when one copy is no narrower than the visible strip; with two or three
     * short headlines it otherwise runs out of content and shows a gap
     * before the restart.
     */
    min-inline-size: 100vw;
}

.news__item {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    padding-inline-start: 26px;
    font-size: 0.84375rem;
    color: rgba(255, 255, 255, 0.78);
    /* Each headline is Arabic on an LTR track — isolate it so punctuation
       stays at the correct end of its own run. */
    direction: rtl;
    unicode-bidi: isolate;
}

html[dir='ltr'] .news__item {
    direction: ltr;
}

.news__sep {
    padding-inline: 26px;
    color: var(--gold-400);
    font-size: 0.5rem;
}

.news__more {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    flex-shrink: 0;
    padding-inline: var(--s-5);
    border-inline-start: 1px solid var(--hairline-gold);
    color: var(--gold-400);
    font-size: 0.78125rem;
    font-weight: 600;
    white-space: nowrap;
}

.news__more:hover,
.news__more:focus-visible {
    color: #fff;
}

.news__more:hover .arrow,
.news__more:focus-visible .arrow {
    transform: translateX(4px);
}

html[dir='rtl'] .news__more:hover .arrow,
html[dir='rtl'] .news__more:focus-visible .arrow {
    transform: scaleX(-1) translateX(4px);
}

/* Below the desktop breakpoint the badge label alone can eat half the
   strip; the strip stays, the trailing link goes. */
@media (max-width: 639px) {
    .news__more {
        display: none;
    }
}
</style>
