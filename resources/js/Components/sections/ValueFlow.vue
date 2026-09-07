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

/*
 * The chain, on a rule.
 *
 * It was eight bordered boxes with a hairline between them, which read as a
 * row of tags rather than as a sequence — the thing the section exists to
 * show is the DISTANCE between owning a skill and shipping a product, and
 * eight equal boxes flatten it.
 *
 * They now sit on one gold rule, each a cut node with its number above it:
 * the same treatment `.steps` gives the six-stage timeline further down the
 * page, so a reader meets one idea of "a sequence" twice rather than two.
 */
.flow__chain {
    position: relative;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: var(--s-6) var(--s-4);
    margin-block-start: var(--s-7);
    padding: 0;
    list-style: none;
    counter-reset: flow;
}

.flow__step {
    position: relative;
    counter-increment: flow;
    padding-block-start: var(--s-6);
    /*
     * Body size, not the small one. These eight are the argument the section
     * makes — the distance between owning a skill and shipping a product —
     * and Arabic at 14px asks a procurement reader to lean in. `--lh-body`
     * resolves to 1.85 on the Arabic site (§10.3), which is the room the
     * script needs and the Latin one does not.
     */
    font-size: var(--fs-body);
    line-height: var(--lh-body);
    color: var(--text);
}

/* The rule, drawn per node rather than once behind them all: it then stops
   at the end of each row instead of running through the gap when the grid
   wraps. */
.flow__step::before {
    content: "";
    position: absolute;
    inset-block-start: 9px;
    inset-inline: 0;
    block-size: 1px;
    background: repeating-linear-gradient(
        to right,
        var(--gold-400) 0 4px,
        transparent 4px 10px
    );
}

/* The node: the company's own octagon, sitting on the rule. */
.flow__step::after {
    content: counter(flow, decimal-leading-zero);
    position: absolute;
    inset-block-start: 0;
    inset-inline-start: 0;
    display: grid;
    place-items: center;
    inline-size: 22px;
    block-size: 22px;
    background: var(--navy-900);
    color: var(--gold-400);
    font-family: var(--font-mono);
    font-size: 10px;
    clip-path: polygon(
        30% 0, 70% 0, 100% 30%, 100% 70%,
        70% 100%, 30% 100%, 0 70%, 0 30%
    );
}

/*
 * Four across on a wide screen, not as many as fit.
 *
 * `auto-fit` gave seven columns at desktop width, which left the eighth step
 * alone on a second row with six empty columns beside it — a hole the width
 * of the page in the middle of the section, and the reason this block looked
 * like it had lost something. Four columns divide the eight evenly into two
 * rows, and each node gets the width its label needs.
 */
@media (min-width: 1024px) {
    .flow__chain {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (prefers-reduced-motion: reduce) {
    .flow__step {
        transition: none;
    }
}
</style>
