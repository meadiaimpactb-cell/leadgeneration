<script setup>
import Container from '@/Components/ui/Container.vue';
import SectionIndex from '@/Components/ui/SectionIndex.vue';
import { useReveal } from '@/Composables/useReveal';
import { useFormat } from '@/Composables/useFormat';

/**
 * sections/Gallery (§11.1) — the showroom.
 *
 * A staggered mosaic rather than an even grid. Four identical squares read as
 * a catalogue, which §2.2 forbids this site from being; three columns at
 * different widths, different crops and different vertical offsets read as an
 * edited spread, which is how a craft house shows work.
 *
 * The three treatments cycle on `index % 3`, so the rhythm holds whether the
 * client uploads three photographs or thirty. Images come from the section's
 * `gallery` media collection — nothing is rendered until they upload some.
 */
defineProps({
    heading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    /**
     * Named `images`, not `items`, because SectionRenderer spreads the
     * section's `settings` straight onto the component and the payload key is
     * `images`. Renaming it would silently empty this section on every other
     * page that renders a gallery.
     */
    images: { type: Array, default: () => [] },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    slug: { type: String, default: null },
});

const { root } = useReveal();
const { number } = useFormat();

const rank = (i) => number(i + 1).padStart(2, '0');
</script>

<template>
    <section
        v-if="images.length"
        ref="root"
        class="section gallery"
        :aria-labelledby="heading ? 'gallery-heading' : undefined"
    >
        <Container>
            <div class="gallery__head">
                <div>
                    <SectionIndex :index="index" :total="total" :slug="slug" />
                    <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
                    <h2 v-if="heading" id="gallery-heading" class="h2 gallery__title reveal">
                        {{ heading }}
                    </h2>
                </div>

                <a v-if="ctaLabel" :href="ctaUrl ?? '#'" class="gallery__cta">
                    {{ ctaLabel }}
                    <span class="arrow" aria-hidden="true">&#8594;</span>
                </a>
            </div>

            <ul class="gallery__grid">
                <li
                    v-for="(item, i) in images"
                    :key="item.url ?? i"
                    class="gallery__cell reveal"
                    :class="`gallery__cell--${i % 3}`"
                >
                    <figure class="gallery__figure">
                        <div class="gallery__frame media-ground">
                            <img
                                class="gallery__img"
                                :src="item.webp ?? item.url"
                                :alt="item.alt ?? ''"
                                :width="item.width ?? undefined"
                                :height="item.height ?? undefined"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>

                        <figcaption v-if="item.caption" class="gallery__caption">
                            <span class="mono-label mono-label--tight mono-label--gold">
                                {{ rank(i) }}
                            </span>
                            {{ item.caption }}
                        </figcaption>
                    </figure>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.gallery__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--s-5);
    margin-block-end: var(--s-8);
}

.gallery__title {
    margin-block-start: var(--s-4);
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

.gallery__cta {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    margin-inline-start: auto;
    flex-shrink: 0;
    white-space: nowrap;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--action-600);
}

.gallery__cta:hover .arrow,
.gallery__cta:focus-visible .arrow {
    transform: translateX(4px);
}

html[dir='rtl'] .gallery__cta:hover .arrow,
html[dir='rtl'] .gallery__cta:focus-visible .arrow {
    transform: scaleX(-1) translateX(4px);
}

.gallery__grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-5);
    align-items: start;
}

.gallery__figure {
    margin: 0;
}

/*
 * The frame carries the aspect ratio and the placeholder ground; the image
 * fills it. Declaring the ratio on the box rather than on the file is what
 * keeps the layout from shifting when the photograph arrives.
 */
.gallery__frame {
    overflow: hidden;
}

.gallery__img {
    inline-size: 100%;
    block-size: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform var(--dur-el) var(--ease);
}

.gallery__cell:hover .gallery__img {
    transform: scale(1.03);
}

.gallery__caption {
    display: flex;
    align-items: baseline;
    gap: var(--s-3);
    margin-block-start: var(--s-3);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

/* Three treatments, cycling. Ratios only — the offsets are desktop-only,
   since a stagger inside a single column is just uneven spacing. */
.gallery__cell--0 .gallery__frame {
    aspect-ratio: 4 / 5;
}
.gallery__cell--1 .gallery__frame {
    aspect-ratio: 3 / 4;
}
.gallery__cell--2 .gallery__frame {
    aspect-ratio: 1 / 1;
}

@media (min-width: 640px) {
    .gallery__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .gallery__grid {
        /* Unequal tracks: the widest column carries the establishing shot. */
        grid-template-columns: 1.5fr 1fr 1.1fr;
        gap: var(--s-6);
    }

    .gallery__cell--1 {
        padding-block-start: 88px;
    }

    .gallery__cell--2 {
        padding-block-start: 34px;
    }
}
</style>
