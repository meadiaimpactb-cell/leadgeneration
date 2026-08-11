<script setup>
import Container from '@/Components/ui/Container.vue';
import SectionIndex from '@/Components/ui/SectionIndex.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/StoryCarousel (§11.1) — artisan stories, portrait + quote.
 *
 * Not a carousel any more, and deliberately so: an auto-advancing rail moves
 * testimony out from under the reader mid-sentence, and the three stories fit
 * on one screen at desktop width. Below that it is a scroll-snap rail, driven
 * by the finger or the keyboard, never by a timer.
 *
 * The columns step down by 56px each so the eye travels the row instead of
 * scanning three equal boxes.
 */
defineProps({
    heading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    items: { type: Array, default: () => [] },
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    slug: { type: String, default: null },
});

const { root } = useReveal();
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="section stories"
        :aria-labelledby="heading ? 'stories-heading' : undefined"
    >
        <Container>
            <SectionIndex :index="index" :total="total" :slug="slug" />
            <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
            <h2 v-if="heading" id="stories-heading" class="h2 stories__title reveal">
                {{ heading }}
            </h2>

            <ul class="stories__rail" tabindex="0" :aria-label="heading ?? undefined">
                <li
                    v-for="(story, i) in items"
                    :key="story.id"
                    class="stories__cell reveal"
                    :style="{ '--step': i % 3 }"
                >
                    <figure class="story">
                        <div v-if="story.image" class="story__frame">
                            <img
                                class="story__image"
                                :src="story.image.webp ?? story.image.url"
                                :alt="story.image.alt ?? ''"
                                :width="story.image.width ?? undefined"
                                :height="story.image.height ?? undefined"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>

                        <figcaption class="story__body">
                            <blockquote v-if="story.quote" class="story__quote">
                                {{ story.quote }}
                            </blockquote>
                            <p v-if="story.attribution" class="story__attribution">
                                {{ story.attribution }}
                            </p>
                        </figcaption>
                    </figure>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.stories__title {
    margin-block-start: var(--s-4);
    margin-block-end: var(--s-8);
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

.stories__rail {
    display: flex;
    gap: var(--gutter);
    padding-block-end: var(--s-4);
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    overscroll-behavior-x: contain;
    scrollbar-width: thin;
}

.stories__cell {
    flex: 0 0 min(82vw, 380px);
    scroll-snap-align: start;
}

.story {
    margin: 0;
}

/*
 * Portrait, not landscape. These are photographs of people at a bench; 4:3
 * crops the maker out at the waist, 4:5 keeps the hands and the piece in the
 * same frame, which is the whole point of the section.
 */
/*
 * No placeholder hatching on this frame, unlike the gallery's.
 *
 * These are product photographs shot on a white sweep, and `multiply` below
 * is what drops that white into the page. Anything painted behind the image —
 * the warm hatch the gallery uses — would show straight through the area the
 * white used to occupy, which is the whole frame minus the piece. The frame
 * carries the page's own ground instead, so the swap is invisible.
 */
.story__frame {
    aspect-ratio: 4 / 5;
    overflow: hidden;
    background: var(--paper-warm);
}

.story__image {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
    /*
     * White × anything = anything, so the studio sweep disappears and the
     * piece sits directly on the paper. It is not a cutout — a shadow or a
     * grey gradient in the original still darkens what is under it — but on
     * a clean white sweep that reads as the piece resting on the page, which
     * is the intent.
     */
    mix-blend-mode: multiply;
}

.story__body {
    margin-block-start: var(--s-6);
}

/*
 * A gold rule beside the quote rather than a quotation glyph: the mark
 * differs between Arabic and Latin typography and would need a literal
 * character in the template either way (§22.5).
 */
.story__quote {
    margin: 0;
    padding-inline-start: var(--s-4);
    border-inline-start: 2px solid var(--gold-400);
    font-size: 1.1875rem;
    font-weight: 500;
    line-height: 1.8;
    color: var(--navy-900);
}

.story__attribution {
    margin-block-start: var(--s-5);
    padding-block-start: var(--s-4);
    border-block-start: 1px solid var(--hairline);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1024px) {
    /*
     * Wide enough for all three at once, so the rail becomes a grid. Snap and
     * overflow are switched off with it — a grid that still declared
     * scroll-snap would trap a trackpad gesture on a page that no longer
     * scrolls sideways.
     */
    .stories__rail {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--s-8);
        overflow-x: visible;
        scroll-snap-type: none;
    }

    .stories__cell {
        flex: initial;
        /* The stagger: 0, 56px, 112px, then back to 0. It cycles on the
           column rather than climbing with the index — a fourth story on a
           second row would otherwise start 168px down and leave a hole. */
        padding-block-start: calc(var(--step, 0) * 56px);
    }
}
</style>
