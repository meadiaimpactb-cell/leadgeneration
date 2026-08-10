<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/FaqAccordion (§11.2) + ui/Accordion.
 *
 * Built on native <details>/<summary>: keyboard support, screen-reader
 * semantics and expand/collapse come from the browser rather than from
 * hand-written ARIA that is easy to get subtly wrong.
 *
 * The page emits matching FAQPage structured data (§13).
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

            <div class="faq">
                <details v-for="(item, i) in items" :key="i" class="faq__item reveal">
                    <summary class="faq__q">
                        <span>{{ item.question }}</span>
                        <span class="faq__marker" aria-hidden="true" />
                    </summary>
                    <div class="faq__a">{{ item.answer }}</div>
                </details>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.faq {
    margin-block-start: var(--s-7);
    max-inline-size: 80ch;
}

.faq__item {
    border-block-end: 1px solid var(--hairline);
}

.faq__q {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-4);
    padding-block: var(--s-5);
    min-block-size: 44px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: var(--fs-h3);
    color: var(--navy-900);
    list-style: none;
}

.faq__q::-webkit-details-marker {
    display: none;
}

/* A plus that becomes a minus — no icon font, no directional glyph to
   mirror in RTL. */
.faq__marker {
    position: relative;
    flex: 0 0 auto;
    inline-size: 16px;
    block-size: 16px;
}

.faq__marker::before,
.faq__marker::after {
    content: '';
    position: absolute;
    inset-block-start: 50%;
    inset-inline-start: 0;
    inline-size: 16px;
    block-size: 2px;
    background: var(--action-600);
    transition: transform var(--dur-micro) var(--ease);
}

.faq__marker::after {
    transform: rotate(90deg);
}

.faq__item[open] .faq__marker::after {
    transform: rotate(0deg);
}

.faq__a {
    padding-block-end: var(--s-5);
    color: var(--text-muted);
    max-inline-size: 68ch;
}
</style>
