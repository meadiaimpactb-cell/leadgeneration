<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * ui/Button — §10.5 inventory.
 *
 * Variants: primary · secondary · ghost · cta · cta-lg · link
 * States:   default · hover · focus-visible · active · disabled · loading
 *
 * Renders as <button>, <a> or Inertia <Link> depending on what it does, so
 * that a thing which navigates is always a real link (§7.2) and a thing which
 * acts is always a button.
 */
const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'ghost', 'cta', 'cta-lg', 'link'].includes(v),
    },
    href: { type: String, default: null },
    // External links get a full page load; internal ones stay in Inertia.
    external: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const tag = computed(() => {
    if (props.href === null) return 'button';
    return props.external ? 'a' : Link;
});

const classes = computed(() => ['btn', `btn--${props.variant}`]);

const isInert = computed(() => props.disabled || props.loading);
</script>

<template>
    <component
        :is="tag"
        :class="classes"
        :href="href ?? undefined"
        :type="href === null ? type : undefined"
        :disabled="href === null ? isInert : undefined"
        :aria-disabled="href !== null && isInert ? 'true' : undefined"
        :aria-busy="loading ? 'true' : undefined"
        :rel="external ? 'noopener noreferrer' : undefined"
        :target="external ? '_blank' : undefined"
    >
        <slot name="icon-start" />
        <slot />
        <slot name="icon-end" />
    </component>
</template>
