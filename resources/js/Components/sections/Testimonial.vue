<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/Testimonial (§10.5) — a single client quote.
 */
defineProps({
    body: { type: String, default: null },
    attribution: { type: String, default: null },
    role: { type: String, default: null },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="body" ref="root" class="section quote">
        <Container>
            <figure class="quote__figure reveal">
                <blockquote class="quote__text">{{ body }}</blockquote>
                <figcaption v-if="attribution" class="quote__by">
                    {{ attribution }}
                    <span v-if="role" class="quote__role">— {{ role }}</span>
                </figcaption>
            </figure>
        </Container>
    </section>
</template>

<style scoped>
.quote {
    background: var(--paper-alt);
}

.quote__figure {
    margin: 0;
    max-inline-size: 56ch;
}

.quote__text {
    margin: 0;
    font-family: var(--font-display);
    font-size: var(--fs-h2);
    line-height: var(--lh-heading);
    color: var(--navy-900);
}

.quote__by {
    margin-block-start: var(--s-5);
    font-weight: 600;
}

.quote__role {
    color: var(--text-muted);
    font-weight: 400;
}
</style>
