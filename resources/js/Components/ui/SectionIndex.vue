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
 *  · the caption comes from the section's own type slug, which is Latin by
 *    definition — the alternative was asking the client to type an English
 *    word into the Arabic site.
 *
 * Digits go through useFormat, so they are Latin in both languages — the
 * Arabic-Indic forms the `ar-SA` default would otherwise produce never reach
 * the page regardless of locale.
 */
const props = defineProps({
    /** 1-based position among the numbered sections. */
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    /** The section type slug, e.g. `intro_statement`. */
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

const caption = computed(() => (props.slug ?? '').replace(/[_-]+/g, ' ').trim() || null);
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
