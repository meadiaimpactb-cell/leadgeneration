<script setup>
/**
 * ui/Divider variant "sadu" — the signature element (§10.1).
 *
 * Permitted in exactly three places on the whole site:
 *   1. between sections (this component)
 *   2. as the faint ground behind the impact numbers (.sadu-ground)
 *   3. as the hover underline on links (.link-weave)
 *
 * There is no fourth use. Reach for <hr class="plain"> instead.
 *
 * Decorative, so it is hidden from assistive technology: a screen reader
 * announcing "separator" between every section is noise, and the heading
 * structure already conveys the divisions.
 */
defineProps({
    variant: {
        type: String,
        default: 'sadu',
        validator: (v) => ['sadu', 'plain'].includes(v),
    },
});
</script>

<template>
    <hr
        v-if="variant === 'sadu'"
        class="sadu-divider"
        role="presentation"
        aria-hidden="true"
    />
    <hr v-else class="plain-divider" role="presentation" aria-hidden="true" />
</template>

<style scoped>
.plain-divider {
    block-size: 1px;
    border: 0;
    margin: 0;
    background: var(--hairline);
}
</style>
