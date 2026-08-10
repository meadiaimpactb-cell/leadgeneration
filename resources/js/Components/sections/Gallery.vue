<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/Gallery (§10.5) — a craft image grid.
 */
defineProps({
    heading: { type: String, default: null },
    images: { type: Array, default: () => [] },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="images.length" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ul class="gallery">
                <li v-for="(image, i) in images" :key="i" class="gallery__item reveal">
                    <img
                        :src="image.webp ?? image.url"
                        :alt="image.alt ?? ''"
                        :width="image.width ?? undefined"
                        :height="image.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />
                    <p v-if="image.caption" class="gallery__caption">{{ image.caption }}</p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.gallery {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: repeat(2, 1fr);
    margin-block-start: var(--s-7);
}

.gallery__item img {
    inline-size: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: var(--r-md);
}

.gallery__caption {
    margin-block-start: var(--s-2);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

@media (min-width: 1024px) {
    .gallery {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
