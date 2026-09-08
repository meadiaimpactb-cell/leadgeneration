<script setup>
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import SectionIndex from '@/Components/ui/SectionIndex.vue';
import { useHashCta } from '@/Composables/useHashCta';

/**
 * The hero of an audience-segment page.
 *
 * Separate from `PageHero`, which is the plain title-and-line hero every
 * other inner page uses. A segment page is a pitch to one buyer, and it
 * earns the full treatment: the running index, two actions, and the
 * photograph cut with the site's octagon and dropped over the hero's lower
 * edge so the navy band does not end on a flat line.
 *
 * Every string is a prop. Nothing here is written (§22.1).
 */
defineProps({
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    slug: { type: String, default: null },
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    image: { type: Object, default: null },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    secondaryLabel: { type: String, default: null },
    secondaryUrl: { type: String, default: null },
});

/**
 * Both actions, not just the first.
 *
 * A segment page carries its own form at the foot, so either button may point
 * at `#lead` — and which of the two does is the client's decision in the
 * panel, not a shape this component should assume. The handler ignores any
 * URL that is not a hash, so wiring both costs nothing when they navigate.
 */
const { onHashCta } = useHashCta();
</script>

<template>
    <section class="shero on-dark">
        <Container>
            <div class="shero__grid">
                <div class="shero__copy">
                    <SectionIndex :index="index" :total="total" :slug="slug" />

                    <h1 v-if="title" class="shero__title">{{ title }}</h1>
                    <p v-if="subtitle" class="shero__sub">{{ subtitle }}</p>

                    <div v-if="ctaLabel || secondaryLabel" class="shero__actions">
                        <Button
                            v-if="ctaLabel"
                            variant="cta-lg"
                            :href="ctaUrl"
                            @click="onHashCta($event, ctaUrl)"
                        >
                            {{ ctaLabel }}
                        </Button>
                        <Button
                            v-if="secondaryLabel"
                            variant="secondary"
                            :href="secondaryUrl"
                            @click="onHashCta($event, secondaryUrl)"
                        >
                            {{ secondaryLabel }}
                        </Button>
                    </div>
                </div>

                <!--
                    The photograph, cut and lifted. `cut-framed` is a gold box
                    behind a cut box: clip-path removes a border along with
                    the corner, so the edge has to be drawn as a layer rather
                    than declared on the image.
                -->
                <figure v-if="image" class="shero__figure cut-framed cut">
                    <img
                        class="shero__image cut"
                        :src="image.webp ?? image.url"
                                :srcset="image.srcset ?? undefined"
                                sizes="(min-width: 900px) 50vw, 100vw"
                        :alt="image.alt ?? ''"
                        :width="image.width ?? undefined"
                        :height="image.height ?? undefined"
                        fetchpriority="high"
                        decoding="async"
                    />
                </figure>
            </div>
        </Container>

        <!-- Sadu use: the woven rule closing a dark band, as on the footer. -->
        <div class="sadu-strip sadu-weave shero__edge" aria-hidden="true" />
    </section>
</template>

<style scoped>
.shero {
    position: relative;
    padding-block: var(--s-9) var(--s-10);
    background: var(--navy-900);
}

.shero__grid {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
    align-items: center;
}

.shero__title {
    margin-block-start: var(--s-4);
    font-family: var(--font-display);
    font-size: clamp(2rem, 3.4vw, 3.25rem);
    font-weight: 700;
    line-height: var(--lh-heading);
    letter-spacing: -0.01em;
    color: #fff;
    max-inline-size: 18ch;
}

.shero__sub {
    margin-block-start: var(--s-5);
    font-size: var(--fs-body-lg);
    line-height: var(--lh-body);
    /* Cream, not pure white — §10.2: white on navy at body size reads cold
       against a warm identity, and the difference is what makes the page
       feel made rather than generated. */
    color: rgba(251, 248, 243, 0.82);
    max-inline-size: 52ch;
}

.shero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-start: var(--s-7);
}

.shero__figure {
    margin: 0;
    /* Dropped below the band's lower edge so the navy does not end on a
       flat line. The strip below is 10px, hence the clearance. */
    margin-block-end: calc(var(--s-8) * -1);
    box-shadow: 0 24px 60px rgba(0, 26, 49, 0.45);
}

.shero__image {
    inline-size: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    background: var(--placeholder-warm);
}

.shero__edge {
    --sadu-tile: 10px;
    --sadu-colour: rgb(var(--gold-rgb) / 0.7);
    position: absolute;
    inset-inline: 0;
    inset-block-end: 0;
}

@media (min-width: 1024px) {
    .shero__grid {
        grid-template-columns: 1.15fr 0.85fr;
        gap: var(--s-10);
    }
}
</style>
