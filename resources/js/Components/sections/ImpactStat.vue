<script setup>
import { computed } from 'vue';
import { useCountUp } from '@/Composables/useCountUp';
import { useFormat } from '@/Composables/useFormat';

/**
 * A single impact number. Split out from ImpactStats so each figure owns its
 * own count-up (§10.7) — a composable cannot be called in a v-for.
 */
const props = defineProps({
    value: { type: Number, required: true },
    suffix: { type: String, default: null },
    label: { type: String, default: null },
    note: { type: String, default: null },
});

const { el, display } = useCountUp(props.value);
const { number } = useFormat();

// Latin digits in both languages — see useFormat for why.
const formatted = computed(() => number(display.value));
</script>

<template>
    <div ref="el" class="stat reveal">
        <p class="stat__value tabular">
            <span>{{ formatted }}</span><span v-if="suffix" class="stat__suffix">{{ suffix }}</span>
        </p>
        <p v-if="label" class="stat__label">{{ label }}</p>
        <p v-if="note" class="stat__note">{{ note }}</p>
    </div>
</template>

<style scoped>
.stat__value {
    font-family: var(--font-body-en);
    font-size: var(--fs-display);
    line-height: var(--lh-display);
    font-weight: 600;
    color: var(--navy-900);
}

.stat__suffix {
    font-size: 0.5em;
    margin-inline-start: var(--s-1);
    color: var(--action-600);
}

.stat__label {
    margin-block-start: var(--s-2);
    font-weight: 600;
}

.stat__note {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}
</style>
