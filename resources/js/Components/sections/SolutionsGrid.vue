<script setup>
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/SolutionsGrid (§11.1) — 3-up grid: icon + name + one line + link.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ul class="grid">
                <li v-for="item in items" :key="item.id" class="reveal">
                    <!-- The whole card is one link: a card with a separate
                         "read more" gives keyboard users two stops for one
                         destination. -->
                    <Link :href="item.url" class="card card--link solution">
                        <span v-if="item.icon" class="solution__icon" aria-hidden="true">
                            {{ item.icon }}
                        </span>
                        <h3 class="solution__name">{{ item.name }}</h3>
                        <p v-if="item.summary" class="solution__summary">{{ item.summary }}</p>
                        <span class="solution__more">{{ t('common.learn_more') }}</span>
                    </Link>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.grid {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.solution {
    block-size: 100%;
}

.solution__icon {
    font-size: var(--fs-h3);
    color: var(--gold-400);
}

.solution__name {
    font-size: var(--fs-h3);
}

.solution__summary {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.solution__more {
    margin-block-start: auto;
    padding-block-start: var(--s-3);
    color: var(--link);
    font-size: var(--fs-sm);
    font-weight: 600;
}

@media (min-width: 640px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
