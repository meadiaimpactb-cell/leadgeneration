<script setup>
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue';

/**
 * A labelled admin form control with its error message.
 *
 * Every input in the panel goes through this so the label/`for` pairing and
 * the `aria-describedby` wiring are correct once rather than per screen.
 */
const props = defineProps({
    label: { type: String, required: true },
    modelValue: { type: [String, Number, Boolean, null], default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    dir: { type: String, default: null },
    placeholder: { type: String, default: null },
    options: { type: Array, default: () => [] },
    rows: { type: Number, default: 4 },
    // Grow the box with what is in it, up to `maxRows`, then scroll. Only
    // meaningful on a textarea.
    autogrow: { type: Boolean, default: false },
    maxRows: { type: Number, default: 10 },
    required: { type: Boolean, default: false },
    // A locked control still renders, so the reader can see the value and
    // why it cannot change — better than hiding it.
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const id = useId();
const describedBy = computed(() =>
    [props.hint ? `${id}-hint` : null, props.error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined
);

/**
 * The box grows with what is written in it — the same behaviour, and the same
 * reasoning, as the message field on the contact form.
 *
 * Height is reset before it is measured: `scrollHeight` only ever grows while
 * the element is taller than its content, so without the reset the box can
 * expand and never shrink back.
 */
const area = ref(null);

function grow() {
    const el = area.value;
    if (!el || !props.autogrow) return;

    // A hidden element reports `scrollHeight: 0`, and sizing to that would
    // collapse the box for good — it is measured again when it is shown.
    if (!el.offsetParent && el.offsetHeight === 0) return;

    const styles = window.getComputedStyle(el);
    const line = parseFloat(styles.lineHeight) || 24;
    const chrome =
        parseFloat(styles.paddingBlockStart) +
        parseFloat(styles.paddingBlockEnd) +
        parseFloat(styles.borderBlockStartWidth) +
        parseFloat(styles.borderBlockEndWidth);

    const max = line * props.maxRows + chrome;

    el.style.height = 'auto';

    const next = Math.min(el.scrollHeight, max);

    el.style.height = `${next}px`;
    // Past the ceiling it scrolls rather than pushing the save button off-screen.
    el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
}

function onAreaInput(event) {
    emit('update:modelValue', event.target.value);
    grow();
}

onMounted(() => nextTick(grow));

// A value that arrives from the server rather than the keyboard — a switched
// provider, a validation bounce — resizes the box too.
watch(() => props.modelValue, () => nextTick(grow));
</script>

<template>
    <div class="field">
        <label class="field__label" :for="id">
            {{ label }}
            <span v-if="required" aria-hidden="true" class="field__req">*</span>
        </label>

        <select
            v-if="type === 'select'"
            :id="id"
            class="field__input"
            :value="modelValue"
            :placeholder="placeholder ?? undefined"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option value="">—</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>

        <label v-else-if="type === 'checkbox'" class="field__check">
            <input
                :id="id"
                type="checkbox"
                :checked="Boolean(modelValue)"
                :disabled="disabled"
                :aria-describedby="describedBy"
                @change="$emit('update:modelValue', $event.target.checked)"
            />
            <span>{{ hint ?? label }}</span>
        </label>

        <!--
            The textarea binds `placeholder` like the other two controls. It
            used to be the one that did not, so exactly the fields with the
            most room to explain themselves — body, excerpt, meta description
            — were the ones that explained nothing.
        -->
        <textarea
            v-else-if="type === 'textarea' || type === 'richtext'"
            :id="id"
            ref="area"
            class="field__input field__input--area"
            :class="{ 'field__input--grow': autogrow }"
            :value="modelValue ?? ''"
            :rows="type === 'richtext' ? 10 : rows"
            :disabled="disabled"
            :dir="dir ?? 'auto'"
            :placeholder="placeholder ?? undefined"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            @input="onAreaInput"
        />

        <input
            v-else
            :id="id"
            class="field__input"
            :type="type === 'slug' ? 'text' : type"
            :value="modelValue ?? ''"
            :dir="dir ?? 'auto'"
            :placeholder="placeholder ?? undefined"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <p v-if="hint && type !== 'checkbox'" :id="`${id}-hint`" class="field__hint">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" class="field__error">{{ error }}</p>
    </div>
</template>

<style scoped>
.field {
    display: flex;
    flex-direction: column;
    gap: var(--s-2);
}

.field__label {
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--navy-900);
}

.field__req {
    color: var(--action-600);
}

.field__input {
    inline-size: 100%;
    min-block-size: 44px;
    padding: var(--s-2) var(--s-3);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    background: var(--paper);
    font-size: var(--fs-body);
}

.field__input--area {
    min-block-size: 96px;
    line-height: 1.7;
    resize: vertical;
}

/*
 * A grown box sets its own height in JS, so it starts at one line rather than
 * the fixed 96px, and breaks anywhere — a JWT is one unbroken word and would
 * otherwise run off the edge instead of wrapping.
 */
.field__input--grow {
    min-block-size: 44px;
    overflow-y: hidden;
    word-break: break-all;
}

.field__input:disabled {
    background: var(--paper-alt);
    color: var(--text-muted);
    cursor: not-allowed;
}

.field__input[aria-invalid='true'] {
    border-color: var(--action-600);
}

.field__check {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    font-size: var(--fs-sm);
}

.field__hint {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.field__error {
    font-size: var(--fs-xs);
    font-weight: 600;
    color: var(--action-600);
}
</style>
