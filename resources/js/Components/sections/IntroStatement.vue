<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/IntroStatement (§11.1) — one large paragraph, no imagery.
 *
 * Deliberately the quietest block on the page: it is the pause after the
 * hero, and its restraint is what lets the Sadu divider above it register.
 */
defineProps({
    body: { type: String, default: null },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="body" ref="root" class="section intro">
        <Container>
            <p class="intro__text reveal">{{ body }}</p>
        </Container>
    </section>
</template>

<style scoped>
.intro__text {
    font-family: var(--font-display);
    font-size: var(--fs-h2);
    line-height: var(--lh-heading);
    color: var(--navy-900);
    max-inline-size: 34ch;
    text-wrap: balance;
}
</style>
