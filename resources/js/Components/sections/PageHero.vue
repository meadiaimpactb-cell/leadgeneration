<script setup>
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';

/**
 * The focused hero used by every inner page (§11.2): title, one line, one CTA.
 *
 * Lighter than the home Hero — inner pages have already earned the visitor's
 * attention, so they get straight to the point.
 */
defineProps({
    title: { type: String, default: null },
    subtitle: { type: String, default: null },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    image: { type: Object, default: null },
});
</script>

<template>
    <section class="phero on-dark">
        <Container>
            <div class="phero__grid">
                <div>
                    <h1 v-if="title">{{ title }}</h1>
                    <p v-if="subtitle" class="phero__sub">{{ subtitle }}</p>
                    <Button v-if="ctaLabel" variant="cta-lg" :href="ctaUrl" class="phero__cta">
                        {{ ctaLabel }}
                    </Button>
                </div>

                <img
                    v-if="image"
                    class="phero__image"
                    :src="image.webp ?? image.url"
                    :alt="image.alt ?? ''"
                    :width="image.width ?? undefined"
                    :height="image.height ?? undefined"
                    fetchpriority="high"
                    decoding="async"
                />
            </div>
        </Container>
    </section>
</template>

<style scoped>
.phero {
    padding-block: var(--s-9) var(--s-10);
}

.phero__grid {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
    align-items: center;
}

.phero__sub {
    margin-block-start: var(--s-4);
    font-size: var(--fs-body-lg);
    color: rgba(255, 255, 255, 0.82);
    max-inline-size: 56ch;
}

.phero__cta {
    margin-block-start: var(--s-7);
}

.phero__image {
    inline-size: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: var(--r-md);
}

@media (min-width: 1024px) {
    .phero__grid {
        grid-template-columns: 1.2fr 0.8fr;
        gap: var(--s-10);
    }
}
</style>
