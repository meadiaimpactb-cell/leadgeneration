<script setup>
import { nextTick, ref, watch } from 'vue';
import LeadField from '@/Components/forms/LeadField.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * forms/LeadModal — the §6.1 field, opened from any button (§10.5).
 *
 * It wraps LeadField rather than reimplementing it. There is one contact
 * field on this site and one submit path; a second copy of that form would be
 * a second place for the two to drift apart.
 *
 * Uses the native <dialog> element, so the browser gives us the focus trap,
 * Escape-to-close, inert background and the top layer for free — all of which
 * are easy to get subtly wrong by hand and are what §10.8 requires.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    heading: { type: String, default: null },
    reassurance: { type: String, default: null },
    submitLabel: { type: String, default: null },
    sectorHint: { type: String, default: null },
    campaign: { type: String, default: null },
});

const emit = defineEmits(['close']);

const { t } = useTranslation();
const dialog = ref(null);

watch(
    () => props.open,
    async (open) => {
        await nextTick();

        const el = dialog.value;

        if (!el) return;

        if (open && !el.open) {
            el.showModal();
            // Focus the field itself, not the close button: the visitor asked
            // to be contacted, so put the cursor where they can type.
            el.querySelector('input[name="contact"]')?.focus();
        } else if (!open && el.open) {
            el.close();
        }
    }
);
</script>

<template>
    <!-- `close` fires for Escape and for the backdrop too, so the parent's
         state stays in step with the browser's. -->
    <dialog ref="dialog" class="modal" :aria-label="heading ?? t('leads.submit')" @close="emit('close')">
        <div class="modal__panel">
            <!-- The close button sits in a row of its own rather than floating
                 over the content. Absolutely positioned, it collided with the
                 heading the moment the client wrote a long one. -->
            <div class="modal__bar">
                <button type="button" class="modal__close" :aria-label="t('common.close')" @click="emit('close')">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                        <path
                            d="M6 6l12 12M18 6L6 18"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                        />
                    </svg>
                </button>
            </div>

            <LeadField
                layout="stacked"
                :heading="heading"
                :reassurance="reassurance"
                :submit-label="submitLabel"
                :sector-hint="sectorHint"
                :campaign="campaign"
            />
        </div>
    </dialog>
</template>

<style scoped>
/*
 * The dialog element itself is treated as the overlay, not as the card.
 *
 * Relying on the browser's own centring does not work here: the UA sheet
 * centres a dialog with `margin: auto`, but only while its width is
 * `fit-content`. The moment an inline-size is set — which any responsive
 * dialog needs — it top-aligns and stretches, which is exactly what shipped:
 * a card pinned to the top edge like a banner. So the dialog fills the
 * viewport and centres the panel itself, with no ambiguity left to the UA.
 */
.modal {
    padding: 0;
    border: 0;
    background: transparent;
    inline-size: 100%;
    max-inline-size: 100%;
    block-size: 100%;
    max-block-size: 100%;
    overflow: auto;
    overscroll-behavior: contain;
}

/* A dialog must stay hidden while closed; `display` is set only when open. */
.modal:not([open]) {
    display: none;
}

.modal[open] {
    display: grid;
    place-items: center;
    padding: var(--s-5);
}

.modal::backdrop {
    background: rgb(0 37 70 / 0.6);
}

.modal__panel {
    inline-size: min(480px, 100%);
    padding: var(--s-5) var(--s-6) var(--s-6);
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: var(--shadow-card);
}

.modal__bar {
    display: flex;
    /* Logical: the close button lands top-left in Arabic, top-right in
       English — the outward corner in each reading direction. */
    justify-content: flex-start;
    margin-block-end: var(--s-2);
    margin-inline-start: calc(var(--s-4) * -1);
}

.modal__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 44px;
    block-size: 44px;
    border-radius: var(--r-sm);
    color: var(--muted);
}

.modal__close:hover {
    background: var(--navy-100);
    color: var(--navy-900);
}

@media (prefers-reduced-motion: no-preference) {
    /* The panel animates, not the dialog: the dialog is now the full-viewport
       overlay, and translating that would move the backdrop with it. */
    .modal[open] .modal__panel {
        animation: modal-in var(--dur-el) var(--ease);
    }

    .modal[open]::backdrop {
        animation: fade-in var(--dur-el) var(--ease);
    }
}

@keyframes modal-in {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
}

@keyframes fade-in {
    from {
        opacity: 0;
    }
}
</style>
