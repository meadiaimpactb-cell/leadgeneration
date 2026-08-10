<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/Timeline (§10.5) — milestones, used on About.
 *
 * The rail sits on the inline-start edge so it follows the page direction
 * without a mirrored rule.
 */
defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
</script>

<template>
    <section v-if="items.length" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ol class="timeline">
                <li v-for="(item, i) in items" :key="i" class="timeline__item reveal">
                    <span v-if="item.year" class="timeline__year tabular">{{ item.year }}</span>
                    <h3 v-if="item.title" class="timeline__title">{{ item.title }}</h3>
                    <p v-if="item.body" class="timeline__body">{{ item.body }}</p>
                </li>
            </ol>
        </Container>
    </section>
</template>

<style scoped>
.timeline {
    margin-block-start: var(--s-7);
    padding-inline-start: var(--s-6);
    border-inline-start: 1px solid var(--hairline);
    max-inline-size: 72ch;
}

.timeline__item {
    position: relative;
    padding-block-end: var(--s-8);
}

.timeline__item::before {
    content: '';
    position: absolute;
    inset-inline-start: calc(var(--s-6) * -1 - 4px);
    inset-block-start: 6px;
    inline-size: 8px;
    block-size: 8px;
    border-radius: var(--r-pill);
    background: var(--gold-400);
}

.timeline__year {
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--action-600);
}

.timeline__title {
    margin-block-start: var(--s-1);
    font-size: var(--fs-h3);
}

.timeline__body {
    margin-block-start: var(--s-2);
    color: var(--text-muted);
}
</style>
