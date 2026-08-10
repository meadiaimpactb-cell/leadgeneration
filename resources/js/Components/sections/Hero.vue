<script setup>
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';

/**
 * sections/Hero (§11.1).
 *
 * Navy ground, generous negative space, one wide headline in the brand face,
 * one supporting line, one primary CTA and one secondary link. Media is a
 * vertically cropped craft image on one side.
 *
 * Every string is a prop from the database. If the client has not entered a
 * headline yet, nothing renders rather than a placeholder shipping to
 * production (§0.1, §22.1).
 */
defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    secondaryLabel: { type: String, default: null },
    secondaryUrl: { type: String, default: null },
    image: { type: Object, default: null },
});
</script>

<template>
    <section class="hero on-dark">
        <Container>
            <div class="hero__grid">
                <div class="hero__copy">
                    <h1 v-if="heading" class="display hero__heading">{{ heading }}</h1>
                    <p v-if="subheading" class="hero__sub">{{ subheading }}</p>

                    <div v-if="ctaLabel || secondaryLabel" class="hero__actions">
                        <Button v-if="ctaLabel" variant="cta-lg" :href="ctaUrl">
                            {{ ctaLabel }}
                        </Button>
                        <Button v-if="secondaryLabel" variant="secondary" :href="secondaryUrl">
                            {{ secondaryLabel }}
                        </Button>
                    </div>
                </div>

                <div v-if="image" class="hero__media">
                    <!--
                        The LCP element on the home page: eager, high priority,
                        explicit dimensions so nothing shifts (§13, §15.1).
                    -->
                    <img
                        :src="image.webp ?? image.url"
                        :alt="image.alt ?? ''"
                        :width="image.width ?? undefined"
                        :height="image.height ?? undefined"
                        fetchpriority="high"
                        decoding="async"
                    />
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.hero {
    padding-block: var(--s-10) var(--s-11);
    /* The header sits transparent over this, so the copy needs clearance. */
    margin-block-start: -72px;
    padding-block-start: calc(72px + var(--s-10));
}

.hero__grid {
    display: grid;
    gap: var(--s-8);
    grid-template-columns: 1fr;
    align-items: center;
}

.hero__heading {
    max-inline-size: 18ch;
}

.hero__sub {
    margin-block-start: var(--s-5);
    font-size: var(--fs-body-lg);
    color: rgba(255, 255, 255, 0.82);
    max-inline-size: 52ch;
}

.hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-start: var(--s-8);
}

.hero__media img {
    inline-size: 100%;
    /* Vertically cropped craft image (§11.1). */
    aspect-ratio: 3 / 4;
    object-fit: cover;
    border-radius: var(--r-md);
}

@media (min-width: 1024px) {
    .hero__grid {
        grid-template-columns: 1.15fr 0.85fr;
        gap: var(--s-10);
    }
}
</style>
