<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * ui/Toast — the site telling you something, in the site's own voice.
 *
 * It replaces a modal that was correct and characterless: a white box with a
 * green tick and an OK button, the kind any framework ships. On a page built
 * this deliberately it read as a component borrowed from somewhere else, and
 * it covered the work to say so.
 *
 * This one sits in the corner on the site's navy, carries the Sadu edge the
 * sections carry, and leaves the page alone underneath it. Nothing here is
 * dismissable-only-by-clicking: it goes on its own, the bar says when, and
 * hovering stops the clock.
 *
 * No library. SweetAlert2's toast would have to be stripped of its type
 * scale, its radius, its palette and its icon set before it stopped looking
 * like SweetAlert2 — at which point the remaining saving is a timer and a
 * transition, which is what this file is.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** `success`, `error` or `info` — decides the mark and the accent. */
    type: { type: String, default: 'success' },
    title: { type: String, default: null },
    message: { type: String, default: null },
    /** Milliseconds on screen; 0 stays until dismissed. */
    duration: { type: Number, default: 5000 },
});

const emit = defineEmits(['close']);

const { t } = useTranslation();

const isError = computed(() => props.type === 'error');

/**
 * A pausable clock.
 *
 * The bar and the timer are two things that must agree, so the timer is the
 * authority and the bar is its picture: under `prefers-reduced-motion` the
 * bar does not animate at all — base.css sees to that — and the toast must
 * still leave on time.
 */
const paused = ref(false);
const timer = ref(null);
const remaining = ref(props.duration);
const startedAt = ref(0);

function start() {
    if (props.duration <= 0 || remaining.value <= 0) return;

    startedAt.value = Date.now();
    timer.value = setTimeout(() => emit('close'), remaining.value);
}

function stop() {
    clearTimeout(timer.value);
    timer.value = null;
}

/** Reading it should not cost you the chance to finish reading it. */
function hold() {
    if (timer.value === null) return;

    stop();
    remaining.value -= Date.now() - startedAt.value;
    paused.value = true;
}

function release() {
    if (!paused.value) return;

    paused.value = false;
    start();
}

watch(
    () => props.open,
    (open) => {
        stop();
        paused.value = false;
        remaining.value = props.duration;

        if (open) start();
    },
    { immediate: true }
);

onBeforeUnmount(stop);

function onKeydown(event) {
    if (event.key === 'Escape') emit('close');
}
</script>

<template>
    <!--
        `role="status"` with `aria-live="polite"`: this is an announcement, not
        a dialogue. It never takes focus — the visitor may still be reading the
        form behind it — so a screen reader is told, and a keyboard user can
        reach the close button in tab order without being trapped there.
    -->
    <Transition name="toast">
        <div
            v-if="open"
            class="toast"
            :class="[`toast--${type}`, { 'is-held': paused }]"
            role="status"
            aria-live="polite"
            @mouseenter="hold"
            @mouseleave="release"
            @focusin="hold"
            @focusout="release"
            @keydown="onKeydown"
        >
            <!-- The Sadu edge the sections carry (§10.1's ninth home). -->
            <span class="sadu-edge sadu-weave toast__edge" aria-hidden="true" />

            <span class="toast__mark" aria-hidden="true">
                <svg viewBox="0 0 44 44">
                    <circle class="toast__ring" cx="22" cy="22" r="20" />
                    <path v-if="!isError" class="toast__glyph" d="M13 22.5l6 6 12-12" />
                    <path v-else class="toast__glyph" d="M22 12v13M22 30v.5" />
                </svg>
            </span>

            <div class="toast__body">
                <p class="toast__title">{{ title }}</p>
                <p v-if="message" class="toast__text">{{ message }}</p>
            </div>

            <button
                type="button"
                class="toast__close"
                :aria-label="t('common.close')"
                @click="emit('close')"
            >
                <svg viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
            </button>

            <!-- How long is left, and it stops when you stop. -->
            <span
                v-if="duration > 0"
                class="toast__timer"
                :style="{ animationDuration: `${duration}ms` }"
                aria-hidden="true"
            />
        </div>
    </Transition>
</template>

<style scoped>
/*
 * `inset-inline-end` puts it top-left in Arabic and top-right in English with
 * one declaration — the reading edge in both, which is where the eye already
 * is after pressing a button on that side.
 */
.toast {
    position: fixed;
    z-index: 70;
    inset-block-start: calc(var(--header-h, 72px) + var(--s-4));
    inset-inline-end: var(--s-4);

    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: start;
    gap: var(--s-3);
    inline-size: min(380px, calc(100vw - var(--s-6)));
    padding-block: var(--s-4);
    padding-inline-end: var(--s-4);
    /* Clear of the woven strip on the reading edge. */
    padding-inline-start: calc(var(--s-4) + 20px);

    overflow: hidden;
    border-radius: var(--r-md);
    background: var(--navy-900);
    color: #fff;
    /* Deeper than the card shadow: this floats over the page rather than
       sitting on it, and on a dark panel a light shadow reads as nothing. */
    box-shadow:
        0 2px 6px rgba(0, 37, 70, 0.24),
        0 18px 40px rgba(0, 37, 70, 0.28);
}

/* The mark of the thing that happened, on the edge opposite the weave. */
.toast::after {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-end: 0;
    inline-size: 4px;
    background: var(--action-600);
}

.toast--error::after {
    background: var(--orange-500);
}

.toast--info::after {
    background: var(--lavender-700);
}

.toast__edge {
    --sadu-tile: 16px;
    --sadu-edge-w: 16px;
    --sadu-colour: rgba(220, 173, 117, 0.55);
}

/* ---- the mark ---------------------------------------------------- */

.toast__mark svg {
    inline-size: 34px;
    block-size: 34px;
}

.toast__ring,
.toast__glyph {
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.toast__ring {
    stroke: var(--gold-400);
    stroke-width: 2;
    stroke-dasharray: 126;
    stroke-dashoffset: 126;
    animation: toast-ring 480ms cubic-bezier(0.2, 0.7, 0.3, 1) forwards;
}

.toast__glyph {
    stroke: var(--gold-400);
    stroke-width: 3;
    stroke-dasharray: 40;
    stroke-dashoffset: 40;
    animation: toast-glyph 380ms cubic-bezier(0.2, 0.7, 0.3, 1) 240ms forwards;
}

.toast--error .toast__ring,
.toast--error .toast__glyph {
    stroke: var(--orange-500);
}

@keyframes toast-ring {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes toast-glyph {
    to {
        stroke-dashoffset: 0;
    }
}

/* ---- the words --------------------------------------------------- */

.toast__body {
    min-inline-size: 0;
}

.toast__title {
    margin: 0;
    font-weight: 700;
    line-height: 1.35;
}

.toast__text {
    margin: var(--s-1) 0 0;
    font-size: var(--fs-sm);
    /* Lighter than the title, not grey: grey on navy loses its contrast. */
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.6;
}

.toast__close {
    display: grid;
    place-items: center;
    inline-size: 28px;
    block-size: 28px;
    margin-block-start: -2px;
    padding: 0;
    border: 0;
    border-radius: var(--r-sm);
    background: none;
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
}

.toast__close svg {
    inline-size: 14px;
    block-size: 14px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.75;
    stroke-linecap: round;
}

.toast__close:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
}

/* ---- the clock --------------------------------------------------- */

.toast__timer {
    position: absolute;
    inset-block-end: 0;
    inset-inline-start: 0;
    block-size: 3px;
    inline-size: 100%;
    background: var(--gold-400);
    animation-name: toast-countdown;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}

.toast.is-held .toast__timer {
    animation-play-state: paused;
}

/*
 * `inline-size`, not `transform: scaleX()`.
 *
 * Scaling would need `transform-origin` to name a side, and there is no
 * logical keyword for it — so the bar would have emptied from the middle in
 * both languages, or from the wrong end in one of them. Shrinking the logical
 * size drains it toward `inset-inline-start`: right-to-left in Arabic,
 * left-to-right in English, each following the reading direction. A 3px bar
 * over five seconds does not need the compositor.
 */
@keyframes toast-countdown {
    to {
        inline-size: 0;
    }
}

/* ---- arrival and departure --------------------------------------- */

.toast-enter-active,
.toast-leave-active {
    transition:
        opacity 300ms cubic-bezier(0.2, 0.7, 0.3, 1),
        transform 300ms cubic-bezier(0.2, 0.7, 0.3, 1);
}

/*
 * It slides in from the edge it lives on. `translateX` is physical, so the
 * direction is chosen by `[dir]` rather than by a logical property — the same
 * approach the form already uses for the one rule that cannot be logical.
 */
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(24px);
}

[dir='rtl'] .toast-enter-from,
[dir='rtl'] .toast-leave-to {
    transform: translateX(-24px);
}

/* ---- small screens ----------------------------------------------- */

@media (max-width: 640px) {
    .toast {
        inset-inline: var(--s-3);
        inline-size: auto;
    }
}

/*
 * §10.7: reduced motion gets final states. The mark is drawn, the entrance is
 * instant — and the countdown bar simply does not move, which is why the
 * timer that closes this is JavaScript and not the animation's end.
 */
@media (prefers-reduced-motion: reduce) {
    .toast__ring,
    .toast__glyph {
        animation: none;
        stroke-dashoffset: 0;
    }

    .toast-enter-active,
    .toast-leave-active {
        transition: none;
    }

    .toast-enter-from,
    .toast-leave-to,
    [dir='rtl'] .toast-enter-from,
    [dir='rtl'] .toast-leave-to {
        transform: none;
    }
}
</style>
