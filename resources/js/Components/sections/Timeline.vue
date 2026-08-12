<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import NodeMark from '@/Components/ui/NodeMark.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * sections/Timeline (§10.5) — milestones, used on About.
 *
 * The rail sits on the inline-start edge so it follows the page direction
 * without a mirrored rule, and each milestone is marked with the same gold
 * node the bridge diagram uses — one `ui/NodeMark`, two lines, so they cannot
 * drift apart on a page that shows both.
 *
 * Years and events are the client's own history. They are the one thing on
 * this page a developer must never draft (§22.1): a founding year invented to
 * fill a container is a fact about the company, published.
 */
const props = defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();

/**
 * A milestone's text in the reader's language — the rule shared by every
 * section whose repeatable content lives in `settings` (`useSettingText`).
 * Without it the English page rendered the Arabic milestones verbatim under
 * an English heading, which §12 forbids outright.
 *
 * The year is not passed through it. A year is the same string in both
 * languages, and `useFormat` already keeps digits Latin in each.
 */
const { text } = useSettingText();

/**
 * Two milestones are not a history — they are the beginning of a list, and
 * they read as a company with nothing behind it, which is the opposite of
 * what this section is for. Below three the section stays away and the panel
 * simply shows nothing published yet.
 */
const enough = computed(() => props.items.length >= 3);
</script>

<template>
    <section v-if="enough" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <ol class="timeline">
                <li v-for="(item, i) in items" :key="i" class="timeline__item reveal">
                    <NodeMark class="timeline__node" :size="22" />
                    <span v-if="item.year" class="timeline__year tabular">{{ item.year }}</span>
                    <h3 v-if="text(item, 'title')" class="timeline__title">
                        {{ text(item, 'title') }}
                    </h3>
                    <p v-if="text(item, 'body')" class="timeline__body">
                        {{ text(item, 'body') }}
                    </p>
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

/* Straddling the rail, not sitting beside it: half the node's own width back
   from the edge, plus the rail's hairline. */
.timeline__node {
    position: absolute;
    inset-inline-start: calc(var(--s-6) * -1 - 11px);
    inset-block-start: 0;
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
