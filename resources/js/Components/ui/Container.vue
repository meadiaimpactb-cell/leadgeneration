<script setup>
/**
 * ui/Container — the 1320px grid container from §10.4.
 *
 * Margins and gutters step with the breakpoints; see tokens.css.
 */
defineProps({
    as: { type: String, default: 'div' },
});
</script>

<template>
    <component :is="as" class="container">
        <slot />
    </component>
</template>
