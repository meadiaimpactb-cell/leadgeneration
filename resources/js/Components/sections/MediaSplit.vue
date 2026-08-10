<script setup>
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/MediaSplit (§11.2) — image beside text.
 *
 * `flip` swaps which side the media sits on. It is a logical swap (grid
 * order), so it follows the page direction and needs no RTL special-casing.
 */
defineProps({
    heading: { type: String, default: null },
    body: { type: String, default: null },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    image: { type: Object, default: null },
    flip: { type: Boolean, default: false },
});

const { root } = useReveal();
</script>

<template>
    <section ref="root" class="section">
        <Container>
            <div class="split" :class="{ 'split--flip': flip }">
                <div class="split__copy reveal">
                    <h2 v-if="heading">{{ heading }}</h2>
                    <p v-if="body" class="split__body">{{ body }}</p>
                    <Button v-if="ctaLabel" variant="secondary" :href="ctaUrl" class="split__cta">
                        {{ ctaLabel }}
                    </Button>
                </div>

                <div v-if="image" class="split__media reveal">
                    <img
                        :src="image.webp ?? image.url"
                        :alt="image.alt ?? ''"
                        :width="image.width ?? undefined"
                        :height="image.height ?? undefined"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.split {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
    align-items: center;
}

.split__body {
    margin-block-start: var(--s-4);
    font-size: var(--fs-body-lg);
    color: var(--text-muted);
    max-inline-size: 54ch;
}

.split__cta {
    margin-block-start: var(--s-6);
}

.split__media img {
    inline-size: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    border-radius: var(--r-md);
}

@media (min-width: 1024px) {
    .split {
        grid-template-columns: 1fr 1fr;
        gap: var(--s-10);
    }

    /* Reordering the grid children, not repositioning them — so this reads
       correctly in both RTL and LTR without a mirrored rule. */
    .split--flip .split__copy {
        order: 2;
    }
}
</style>
