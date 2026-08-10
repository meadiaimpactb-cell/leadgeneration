<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/PartnersLogos (§11.1) — greyscale logo strip, colour on hover.
 *
 * Logos are never mirrored in RTL (§12): only directional icons flip.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section partners">
        <Container>
            <h2 v-if="heading" class="partners__heading reveal">{{ heading }}</h2>

            <ul class="partners__grid">
                <li v-for="partner in items" :key="partner.id" class="partners__item reveal">
                    <img
                        v-if="partner.logo"
                        class="partners__logo"
                        :src="partner.logo.url"
                        :alt="partner.name"
                        :width="partner.logo.width ?? undefined"
                        :height="partner.logo.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />
                    <span v-else class="partners__name">{{ partner.name }}</span>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.partners__heading {
    font-size: var(--fs-h3);
    color: var(--text-muted);
}

.partners__grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--s-7) var(--gutter);
    align-items: center;
    margin-block-start: var(--s-7);
}

.partners__item {
    display: flex;
    align-items: center;
    justify-content: center;
}

.partners__logo {
    max-block-size: 48px;
    inline-size: auto;
    object-fit: contain;
    filter: grayscale(1);
    opacity: 0.7;
    transition:
        filter var(--dur-el) var(--ease),
        opacity var(--dur-el) var(--ease);
}

.partners__item:hover .partners__logo {
    filter: grayscale(0);
    opacity: 1;
}

.partners__name {
    color: var(--text-muted);
    font-size: var(--fs-sm);
    text-align: center;
}

@media (min-width: 640px) {
    .partners__grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .partners__grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
</style>
