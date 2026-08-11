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
    /**
     * The metric's own slug. Latin by definition, which is what makes it the
     * right source for the mono caption — the alternative was a second
     * translated field the client would have to fill in Latin on the Arabic
     * site, which is not a thing they should ever be asked to do.
     */
    metricKey: { type: String, default: null },
});

const { el, display } = useCountUp(props.value);
const { number } = useFormat();

// Latin digits in both languages — see useFormat for why.
const formatted = computed(() => number(display.value));

/** `pieces_delivered` → `PIECES DELIVERED`. */
const caption = computed(() => (props.metricKey ?? '').replace(/[_-]+/g, ' ').trim() || null);
</script>

<template>
    <div ref="el" class="stat reveal">
        <p v-if="caption" class="stat__caption mono-label mono-label--tight">{{ caption }}</p>

        <p class="stat__value tabular">
            <span>{{ formatted }}</span><span v-if="suffix" class="stat__suffix">{{ suffix }}</span>
        </p>

        <p v-if="label" class="stat__label">{{ label }}</p>
        <p v-if="note" class="stat__note">{{ note }}</p>
    </div>
</template>

<style scoped>
.stat__caption {
    color: var(--action-600);
    margin-block-end: var(--s-3);
}

.stat__value {
    font-family: var(--font-body-en);
    font-size: var(--stat-size, 2.375rem);
    line-height: 1;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--navy-900);
}

.stat__suffix {
    font-size: 0.5em;
    margin-inline-start: var(--s-1);
    color: var(--action-600);
}

.stat__label {
    margin-block-start: var(--s-1);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

.stat__note {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

/* On a dark band the same three lines invert, and the figure grows: it is
   the section rather than a caption under a hero. */
.on-dark .stat__caption {
    color: var(--gold-400);
}

.on-dark .stat__value {
    color: #fff;
    font-size: var(--stat-size, clamp(2.75rem, 5.2vw, 4.625rem));
    line-height: 0.95;
}

.on-dark .stat__label {
    color: var(--gold-400);
    font-size: 1.03125rem;
    font-weight: 600;
}

.on-dark .stat__note {
    color: rgba(255, 255, 255, 0.55);
}
</style>
