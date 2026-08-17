<script setup>
import { computed } from 'vue';
import { useFormat } from '@/Composables/useFormat';

/**
 * ui/SectionIndex — "01 / 06 · MANIFESTO".
 *
 * The running number that turns the home page from a stack of blocks into a
 * document with a spine. Both halves are derived, never written:
 *
 *  · the position and the total come from the section's place in the
 *    `sections` rows, so deleting a block in the admin panel renumbers the
 *    rest instead of leaving a gap;
 *  · the caption arrives already finished — either the label the client wrote
 *    on the section in the panel, or the shipped label for its type.
 *
 * IT IS PRINTED VERBATIM. This component used to rewrite `_` and `-` into
 * spaces, from when the prop really was a type slug (`intro_statement`). Once
 * the client could write the caption themselves that stopped being a tidy-up
 * and became an edit: a label typed as "chosen-in-panel" reached the page as
 * "chosen in panel". Whoever passes a slug humanises it at the call site —
 * today that is Pages/Public/Sector.vue and nowhere else.
 *
 * Digits go through useFormat, so they are Latin in both languages — the
 * Arabic-Indic forms the `ar-SA` default would otherwise produce never reach
 * the page regardless of locale.
 */
const props = defineProps({
    /** 1-based position among the numbered sections. */
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    /** The finished caption, e.g. `manifesto`. Printed as given. */
    slug: { type: String, default: null },
    /**
     * `inline` puts counter and caption on one line, separated by a middle
     * dot — the form every section heading uses.
     *
     * `stacked` gives each its own line, for the narrow margin column beside
     * the manifesto. It is not only a style choice: on one line in a 190px
     * column the string wraps mid-run, and a wrapped bidi fragment reorders —
     * which is how "01 / 05" came to render as "05 / 01".
     */
    layout: {
        type: String,
        default: 'inline',
        validator: (v) => ['inline', 'stacked'].includes(v),
    },
});

const { number } = useFormat();

const pad = (n) => number(n).padStart(2, '0');

const counter = computed(() =>
    props.index && props.total ? `${pad(props.index)} / ${pad(props.total)}` : null
);

const caption = computed(() => (props.slug ?? '').trim() || null);
</script>

<template>
    <p v-if="(counter || caption) && layout === 'inline'" class="mono-label mono-label--action">
        <template v-if="counter">{{ counter }}</template>
        <span v-if="counter && caption" aria-hidden="true"> &#183; </span>
        <template v-if="caption">{{ caption }}</template>
    </p>

    <!-- Two elements, not one wrapped string: each is its own bidi run and
         neither can reorder against the other. -->
    <div v-else-if="counter || caption" class="index">
        <p v-if="counter" class="mono-label mono-label--action">{{ counter }}</p>
        <p v-if="caption" class="mono-label mono-label--muted index__caption">{{ caption }}</p>
    </div>
</template>

<style scoped>
.index__caption {
    margin-block-start: var(--s-2);
}
</style>
