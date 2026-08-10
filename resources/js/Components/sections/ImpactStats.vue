<script setup>
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import ImpactStat from '@/Components/sections/ImpactStat.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/ImpactStats (§11.1) — 3–4 large numbers over the faint Sadu ground.
 *
 * This is the second of the signature element's three permitted appearances
 * (§10.1). The ground is set at 22% opacity so it reads as texture behind the
 * figures rather than pattern competing with them.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section impact sadu-ground">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <div class="impact__grid">
                <ImpactStat
                    v-for="item in items"
                    :key="item.id"
                    :value="item.value"
                    :suffix="item.suffix"
                    :label="item.label"
                    :note="item.note"
                />
            </div>

            <div v-if="ctaLabel" class="impact__cta reveal">
                <Button variant="secondary" :href="ctaUrl">{{ ctaLabel }}</Button>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.impact__grid {
    display: grid;
    gap: var(--s-7);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.impact__cta {
    margin-block-start: var(--s-8);
}

@media (min-width: 640px) {
    .impact__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .impact__grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
