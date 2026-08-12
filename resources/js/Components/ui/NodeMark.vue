<script setup>
/**
 * ui/NodeMark — the gold node.
 *
 * The octagon of the image signature (§10.3) shrunk to a marker, with a solid
 * centre so it reads as a point ON a thread rather than as a small empty box.
 * It marks a station on any gold line: a stage of the bridge diagram, a
 * milestone on the timeline.
 *
 * One component rather than two near-identical SVGs, because the two lines it
 * serves sit on the same page and a node that is a hair different on one of
 * them is the kind of thing nobody can name and everybody notices.
 *
 * `fill` is the ground the node is punched out of — the octagon is opaque so
 * the thread does not show through its middle.
 */
defineProps({
    size: { type: Number, default: 28 },
    fill: { type: String, default: 'var(--paper-warm)' },
});
</script>

<template>
    <svg
        class="node-mark"
        :width="size"
        :height="size"
        viewBox="0 0 32 32"
        aria-hidden="true"
        focusable="false"
    >
        <path
            d="M10.5 1.5 H21.5 L30.5 10.5 V21.5 L21.5 30.5 H10.5 L1.5 21.5 V10.5 Z"
            :fill="fill"
            stroke="currentColor"
            stroke-width="1.5"
        />
        <circle cx="16" cy="16" r="4.5" fill="currentColor" />
    </svg>
</template>

<style scoped>
.node-mark {
    display: block;
    color: var(--gold-400);
}
</style>
