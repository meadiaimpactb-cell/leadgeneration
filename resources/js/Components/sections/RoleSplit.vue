<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * sections/RoleSplit — who does what, in two columns.
 *
 * The partners section divides the work between the partner and Amad Craft.
 * Written as two sentences the division is a claim; set as two columns it is
 * a boundary the reader can check their own contract against — which is the
 * point a partner is weighing when they read it.
 *
 * Two columns rather than `audience_split`: that component addresses two
 * different audiences, each with its own way in. This addresses one audience
 * about two roles, and neither column is a call to action.
 */
const props = defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    /** The closing line under the split, authored in the panel. */
    body: { type: String, default: null },
    /** `[{ title, body }]`, each doubled with an `_en`. Two of them. */
    columns: { type: Array, default: () => [] },
});

const { root } = useReveal();
const { text } = useSettingText();

const written = computed(() =>
    props.columns
        .map((column) => ({ title: text(column, 'title'), body: text(column, 'body') }))
        .filter((column) => column.title || column.body)
);
</script>

<template>
    <section v-if="written.length" ref="root" class="section split">
        <Container>
            <h2 v-if="heading" class="h2 reveal">{{ heading }}</h2>
            <p v-if="subheading" class="split__sub reveal">{{ subheading }}</p>

            <dl class="split__grid reveal">
                <div v-for="(column, i) in written" :key="i" class="split__col">
                    <dt v-if="column.title" class="split__role">{{ column.title }}</dt>
                    <dd v-if="column.body" class="split__body">{{ column.body }}</dd>
                </div>
            </dl>

            <div v-if="body" class="prose prose__body split__note reveal" v-html="body" />
        </Container>
    </section>
</template>

<style scoped>
.split__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.split__grid {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-6);
}

.split__col {
    padding-block-start: var(--s-4);
    /* The gold hairline is §10.2's divider role, not a fourth Sadu home. */
    border-block-start: 2px solid var(--gold-400);
}

.split__role {
    font-size: var(--fs-h3);
    font-weight: 700;
}

.split__body {
    margin-block-start: var(--s-2);
    margin-inline-start: 0;
    color: var(--text-muted);
}

.split__note {
    margin-block-start: var(--s-6);
}

@media (min-width: 768px) {
    .split__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
