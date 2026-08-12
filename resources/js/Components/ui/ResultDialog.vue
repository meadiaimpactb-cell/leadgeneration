<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * ui/ResultDialog — what happened, said properly.
 *
 * The lead form used to answer a submission by replacing itself with a line of
 * text. It read as a failure: half the band went empty, the thing the visitor
 * had just been using vanished, and the only evidence of success was a
 * sentence where a form had been.
 *
 * So the form stays where it is and this arrives over it. Written here rather
 * than pulled in as a library — SweetAlert and its kin bring their own type
 * scale, their own radius and their own idea of a green, and the one thing a
 * confirmation must not look like on this site is a component from somewhere
 * else. This is roughly forty lines of behaviour; the dependency would have
 * been larger than the thing it replaced.
 *
 * Nothing in it knows about leads: it takes a tone, a title and a line of
 * text, so the next form on this site uses the same dialog.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** `success` or `error` — decides the mark and the accent, nothing else. */
    tone: { type: String, default: 'success' },
    title: { type: String, default: null },
    message: { type: String, default: null },
    /** Milliseconds until it closes itself; 0 to stay until dismissed. */
    autoCloseMs: { type: Number, default: 5000 },
});

const emit = defineEmits(['close']);

const { t } = useTranslation();

const panel = ref(null);
const closeButton = ref(null);
const timer = ref(null);

/** Whatever had focus before the dialog opened, so it can be handed back. */
const previouslyFocused = ref(null);

const isError = computed(() => props.tone === 'error');

watch(
    () => props.open,
    async (open) => {
        clearTimeout(timer.value);

        if (!open) {
            // Focus goes back where the visitor left it — for a keyboard user
            // this is the difference between carrying on and starting over
            // from the top of the page (§10.8).
            previouslyFocused.value?.focus?.();
            previouslyFocused.value = null;

            return;
        }

        previouslyFocused.value = document.activeElement;

        await nextTick();
        closeButton.value?.$el?.focus?.() ?? closeButton.value?.focus?.();

        if (props.autoCloseMs > 0) {
            // It closes itself so a confirmation never becomes a chore, but
            // only ever a confirmation: an error stays until it is read.
            timer.value = setTimeout(() => emit('close'), props.autoCloseMs);
        }
    }
);

onBeforeUnmount(() => clearTimeout(timer.value));

/**
 * Keep focus inside while it is open.
 *
 * A short list of controls, so the cycle is done by hand rather than with a
 * library — and it is required, not decoration: focus that walks out of a
 * modal into the page behind it leaves a keyboard user tabbing through a form
 * they cannot see.
 */
function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');

        return;
    }

    if (event.key !== 'Tab') return;

    const focusable = panel.value?.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    if (!focusable?.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
</script>

<template>
    <Transition name="dialog">
        <div v-if="open" class="dialog" @keydown="onKeydown">
            <!-- Clicking away dismisses it. The scrim is not a control, so it
                 carries no role and no label. -->
            <div class="dialog__scrim" @click="emit('close')" />

            <div
                ref="panel"
                class="dialog__panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="`dialog-title-${tone}`"
                :aria-describedby="`dialog-text-${tone}`"
            >
                <!--
                    The mark draws itself: one stroke, 420ms.
                    `prefers-reduced-motion` renders it already drawn (§10.7).
                -->
                <svg
                    class="dialog__mark"
                    :class="{ 'is-error': isError }"
                    viewBox="0 0 52 52"
                    aria-hidden="true"
                >
                    <circle class="dialog__ring" cx="26" cy="26" r="24" />
                    <path v-if="!isError" class="dialog__glyph" d="M14 27l8 8 16-16" />
                    <path v-else class="dialog__glyph" d="M26 15v16M26 37v.5" />
                </svg>

                <h2 :id="`dialog-title-${tone}`" class="dialog__title">{{ title }}</h2>
                <p :id="`dialog-text-${tone}`" class="dialog__text">{{ message }}</p>

                <button ref="closeButton" type="button" class="dialog__button" @click="emit('close')">
                    {{ t('common.done') }}
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.dialog {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: grid;
    place-items: center;
    padding: var(--s-4);
}

.dialog__scrim {
    position: absolute;
    inset: 0;
    background: rgba(0, 37, 70, 0.6);
}

.dialog__panel {
    position: relative;
    inline-size: min(420px, 100%);
    padding: var(--s-6) var(--s-5) var(--s-5);
    border-radius: var(--r-md);
    background: var(--paper);
    text-align: center;
    box-shadow:
        0 1px 2px rgba(0, 37, 70, 0.06),
        0 8px 24px rgba(0, 37, 70, 0.06);
}

.dialog__mark {
    inline-size: 72px;
    block-size: 72px;
    margin-block-end: var(--s-4);
}

.dialog__ring,
.dialog__glyph {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* Gold for the ring — the identity's premium hairline, and the one colour
   here that is not asking to be clicked. */
.dialog__ring {
    stroke: var(--gold-400, #DCAD75);
    stroke-width: 2;
    stroke-dasharray: 151;
    stroke-dashoffset: 151;
    animation: dialog-ring 520ms cubic-bezier(0.2, 0.7, 0.3, 1) forwards;
}

.dialog__glyph {
    stroke: var(--navy-900, #002546);
    stroke-width: 3.5;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: dialog-glyph 420ms cubic-bezier(0.2, 0.7, 0.3, 1) 260ms forwards;
}

.dialog__mark.is-error .dialog__ring {
    stroke: var(--action-600, #b4522c);
}

.dialog__mark.is-error .dialog__glyph {
    stroke: var(--action-600, #b4522c);
}

@keyframes dialog-ring {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes dialog-glyph {
    to {
        stroke-dashoffset: 0;
    }
}

.dialog__title {
    margin: 0 0 var(--s-2);
    font-size: var(--fs-h3);
}

.dialog__text {
    margin: 0 0 var(--s-5);
    color: var(--muted);
}

.dialog__button {
    min-block-size: 44px;
    padding-inline: var(--s-6);
    border: 0;
    border-radius: var(--r-sm);
    background: var(--navy-900, #002546);
    color: #fff;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.dialog__button:hover {
    background: var(--navy-800, #06345c);
}

.dialog-enter-active,
.dialog-leave-active {
    transition: opacity 160ms cubic-bezier(0.2, 0.7, 0.3, 1);
}

.dialog-enter-active .dialog__panel,
.dialog-leave-active .dialog__panel {
    transition: transform 300ms cubic-bezier(0.2, 0.7, 0.3, 1);
}

.dialog-enter-from,
.dialog-leave-to {
    opacity: 0;
}

.dialog-enter-from .dialog__panel {
    transform: translateY(8px);
}

/* §10.7: reduced motion gets the final state, not a slower animation. */
@media (prefers-reduced-motion: reduce) {
    .dialog__ring,
    .dialog__glyph {
        animation: none;
        stroke-dashoffset: 0;
    }

    .dialog-enter-active,
    .dialog-leave-active,
    .dialog-enter-active .dialog__panel,
    .dialog-leave-active .dialog__panel {
        transition: none;
    }

    .dialog-enter-from .dialog__panel {
        transform: none;
    }
}
</style>
