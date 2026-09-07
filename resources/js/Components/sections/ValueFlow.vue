<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * sections/ValueFlow — a capability chain, shown as a chain.
 *
 * The approved copy lists eight things owning a skill does not give you,
 * written as one arrow-separated line. Set as a paragraph it reads as a list
 * of complaints; set as a sequence it reads as the distance between a craft
 * and a product, which is the argument the section is making.
 *
 * The arrows are drawn by CSS from the writing direction, not typed into the
 * content, so the same eight steps point correctly in Arabic and in English
 * without a second copy of the list (§12).
 */
const props = defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    /** Intro prose above the chain, authored in the panel. */
    body: { type: String, default: null },
    /** `[{ title, title_en }]` from the section's settings. */
    steps: { type: Array, default: () => [] },
});

const { root } = useReveal();
const { text } = useSettingText();

const written = computed(() => props.steps.map((step) => text(step, 'title')).filter(Boolean));
</script>

<template>
    <section v-if="written.length || body" ref="root" class="section flow">
        <Container>
            <h2 v-if="heading" class="h2 reveal">{{ heading }}</h2>
            <p v-if="subheading" class="flow__sub reveal">{{ subheading }}</p>

            <div v-if="body" class="prose prose__body reveal" v-html="body" />

            <!--
                A list, because it is one. The arrow between items is a CSS
                pseudo-element and is hidden from assistive technology: a
                screen reader should hear eight steps, not eight steps and
                seven arrows.
            -->
            <ol v-if="written.length" class="flow__chain reveal">
                <li v-for="(step, i) in written" :key="i" class="flow__step">
                    {{ step }}
                </li>
            </ol>
        </Container>
    </section>
</template>

<style scoped>
.flow__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.flow__chain {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: var(--s-3);
    margin-block-start: var(--s-6);
    padding: 0;
    list-style: none;
    counter-reset: flow;
}

.flow__step {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--s-2);
    padding: var(--s-3) var(--s-4);
    border: 1px solid var(--hairline);
    border-radius: var(--r-md);
    background: var(--paper);
    font-size: var(--fs-sm);
    line-height: 1.5;
}

.flow__step::before {
    counter-increment: flow;
    content: counter(flow, decimal-leading-zero);
    font-family: var(--font-mono);
    font-size: var(--fs-xs);
    color: var(--gold-400);
}

/*
 * The connector. `::after` sits on the inline-end of every step but the last,
 * and the glyph itself is mirrored by the writing direction — so the chain
 * reads right-to-left in Arabic and left-to-right in English from one rule.
 * No `left`/`right` anywhere (§22.6).
 */
.flow__step:not(:last-child)::after {
    content: "";
    position: absolute;
    inset-inline-end: calc(var(--s-3) * -1);
    inline-size: var(--s-3);
    block-size: 1px;
    background: var(--hairline-gold);
}

@media (prefers-reduced-motion: reduce) {
    .flow__step {
        transition: none;
    }
}
</style>
