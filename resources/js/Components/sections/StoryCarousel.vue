<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/StoryCarousel (§11.1) — artisan stories, image + quote.
 *
 * A CSS scroll-snap rail rather than a JS carousel: it costs no JavaScript,
 * works with a trackpad, a touch swipe and the keyboard, follows the page's
 * RTL direction without any mirroring code, and degrades to a plain list.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>
        </Container>

        <!-- Focusable and labelled so a keyboard user can scroll the rail. -->
        <ul class="rail" tabindex="0" :aria-label="heading ?? undefined">
            <li v-for="story in items" :key="story.id" class="rail__item">
                <figure class="story">
                    <img
                        v-if="story.image"
                        class="story__image"
                        :src="story.image.webp ?? story.image.url"
                        :alt="story.image.alt ?? ''"
                        :width="story.image.width ?? undefined"
                        :height="story.image.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />
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
    </section>
</template>

<style scoped>
.rail {
    display: flex;
    gap: var(--gutter);
    margin-block-start: var(--s-7);
    padding-inline: var(--margin);
    padding-block-end: var(--s-4);
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    overscroll-behavior-x: contain;
    scrollbar-width: thin;
}

.rail__item {
    flex: 0 0 min(82vw, 380px);
    scroll-snap-align: start;
}

.story {
    margin: 0;
    block-size: 100%;
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}

.story__image {
    inline-size: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: var(--r-md);
}

.story__quote {
    margin: 0;
    font-family: var(--font-display);
    font-size: var(--fs-h3);
    line-height: var(--lh-heading);
    color: var(--navy-900);
}

.story__attribution {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1440px) {
    /* Align the first card with the container edge on wide screens. */
    .rail {
        padding-inline: max(var(--margin), calc((100vw - var(--container)) / 2));
    }
}
</style>
