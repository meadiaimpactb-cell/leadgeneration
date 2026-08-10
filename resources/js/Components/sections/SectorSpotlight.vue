<script setup>
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/SectorSpotlight (§11.1) — the four audience segments as cards
 * (government / private / partners / artisans).
 *
 * Order comes from `sort_order`, which the client controls; the priority in
 * §3 is the default the seeder sets, not something hard-coded here.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section sectors">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ul class="sectors__grid">
                <li v-for="item in items" :key="item.id" class="reveal">
                    <Link :href="item.url" class="card card--link sector">
                        <h3 class="sector__name">{{ item.name }}</h3>
                        <p v-if="item.summary" class="sector__summary">{{ item.summary }}</p>
                    </Link>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.sectors {
    background: var(--paper-alt);
}

.sectors__grid {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.sector {
    block-size: 100%;
    background: var(--paper);
}

.sector__name {
    font-size: var(--fs-h3);
}

.sector__summary {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 640px) {
    .sectors__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .sectors__grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
