<script setup>
/**
 * ui/Badge (§10.5). Variants: sector · year · status · accent.
 */
defineProps({
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'accent', 'sector', 'year', 'status'].includes(v),
    },
});
</script>

<template>
    <span class="badge" :class="variant === 'accent' ? 'badge--accent' : null">
        <slot />
    </span>
</template>
