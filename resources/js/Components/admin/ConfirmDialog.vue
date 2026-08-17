<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { settle, state } from '@/admin/confirm';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * admin/ConfirmDialog — "are you sure?", in the panel's own voice.
 *
 * Mounted once by AdminLayout and driven by `admin/confirm.js`, which is where
 * the reasoning for replacing the browser's `confirm()` is written.
 *
 * Two things this owes the reader that a native dialog did not:
 *
 * - Focus. Opening moves focus to the cancelling button, not the destructive
 *   one, so Enter on a dialog nobody read does nothing. Closing returns focus
 *   to whatever opened it, so the keyboard does not land back at the top of
 *   the page.
 * - Escape. Dismissing is always available and always means "no".
 *
 * No `left`/`right` anywhere, and no button-order switch by locale: the panel
 * sets `dir` on the document and the row follows it (§22.6).
 */
const { t } = useTranslation();

const panel = ref(null);
const dismissButton = ref(null);
const opener = ref(null);

const confirmLabel = computed(() => state.confirmLabel ?? t('admin.confirm_yes'));

watch(
    () => state.open,
    async (open) => {
        if (open) {
            opener.value = document.activeElement;
            await nextTick();
            // The dismissing control, never the destructive one: Enter on a
            // dialog nobody has read must do nothing. It is the X in the
            // header now that the row below carries only the confirm — a
            // second "cancel" beside it repeated a control already on screen.
            dismissButton.value?.focus();

            return;
        }

        // Only restore focus if the opener is still on the page; a row that
        // was just deleted is not somewhere to put the cursor.
        if (opener.value?.isConnected) {
            opener.value.focus();
        }

        opener.value = null;
    }
);

function onKeydown(event) {
    if (!state.open) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        settle(false);

        return;
    }

    if (event.key !== 'Tab') return;

    // Hold the keyboard inside the dialog while it is open, or tabbing walks
    // into the page underneath and the editor answers a question they can no
    // longer see.
    const focusable = panel.value?.querySelectorAll('button');

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

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKeydown);
}

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKeydown);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition name="confirm">
            <div
                v-if="state.open"
                class="confirm"
                role="presentation"
                @click.self="settle(false)"
            >
                <!--
                    `cut-framed` is a gold box behind a cut box: `clip-path`
                    takes the border off with the corner, so a gold edge has to
                    be a layer rather than a declaration.
                -->
                <div class="confirm__frame cut-framed">
                    <div
                        ref="panel"
                        class="confirm__panel cut"
                        role="alertdialog"
                        aria-modal="true"
                        :aria-label="state.title ?? t('admin.confirm_title')"
                    >
                        <header class="confirm__head">
                            <h2 class="confirm__title">{{ state.title ?? t('admin.confirm_title') }}</h2>

                            <!--
                                Sits opposite the title with no locale test:
                                `space-between` on a row that inherits the
                                document's direction puts it left of an Arabic
                                heading and right of an English one (§22.6).
                            -->
                            <button
                                ref="dismissButton"
                                type="button"
                                class="confirm__dismiss"
                                :title="t('admin.cancel')"
                                :aria-label="t('admin.cancel')"
                                @click="settle(false)"
                            >
                                <NavIcon name="close" :size="18" :muted="false" />
                            </button>
                        </header>

                        <p v-if="state.message" class="confirm__message">{{ state.message }}</p>

                        <div class="confirm__acts">
                            <!--
                                The action colour, on a background rather than as
                                text: `#D7653B` only clears AA on white at 18px/700
                                and above, which is not what a dialog button is.
                            -->
                            <button
                                type="button"
                                class="btn btn--cta act"
                                @click="settle(true)"
                            >
                                <NavIcon name="check" :size="18" :muted="false" />
                                <span>{{ confirmLabel }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.confirm {
    position: fixed;
    inset: 0;
    z-index: 200;
    display: grid;
    place-items: center;
    padding: var(--s-5);
    background: rgb(0 0 0 / 0.45);
}

/*
 * The octagon cut, not a rounded rectangle.
 *
 * The chamfer is the company's own signature, taken from its gift boxes and
 * display cases — so a dialog wearing it reads as an Amad Craft object rather
 * than as a component any framework ships. `clip-path` and never
 * `border-radius`: a chamfer is a straight cut, and rounding it turns a made
 * object back into a web component. Symmetrical on both axes, so unlike the
 * hero's tooth edge it needs no mirroring in LTR.
 *
 * Deliberately NO Sadu. The thread is a closed vocabulary of seven placements
 * (§10.1, listed at the top of components.css) and this would be an eighth.
 * Boldness is spent once; a confirmation prompt is not where to spend it.
 */
.confirm__frame {
    inline-size: min(28rem, 100%);
    box-shadow: 0 1.5rem 3rem rgb(0 0 0 / 0.25);
}

.confirm__panel {
    padding: var(--s-6);
    background: var(--paper);
}

/*
 * The rule under the title is a GOLD hairline, the same rule the sections use.
 * §3 of the design system: depth here is gold hairlines, not heavy shadows or
 * a grey line borrowed from a UI kit. It sits on the header rather than on the
 * message so a dialog with a title and no body still has it.
 */
.confirm__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-4);
    padding-block-end: var(--s-4);
    border-block-end: 1px solid var(--hairline-gold);
}

.confirm__title {
    margin: 0;
    font-size: var(--t-title);
    font-weight: 700;
    color: var(--navy-900);
}

/* Depth is gold hairlines here, not heavy rules. */
.confirm__dismiss {
    display: grid;
    place-items: center;
    inline-size: 32px;
    block-size: 32px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--navy-900);
    cursor: pointer;
    transition: background var(--dur-el) var(--ease);
}

.confirm__dismiss:hover {
    background: var(--navy-100);
}

.confirm__message {
    margin-block: var(--s-5) 0;
    font-size: var(--t-body);
    line-height: 1.7;
    color: var(--text-muted);
}

.confirm__acts {
    display: flex;
    justify-content: flex-end;
    gap: var(--s-3);
    margin-block-start: var(--s-6);
}

/*
 * The panel's `.act` rule is scoped to `.shell`, and this dialog teleports to
 * `<body>` — outside it. The icon/label pairing is restated here rather than
 * unscoping the panel rule, which would leak an admin layout class onto the
 * public site.
 */
.confirm__acts .btn {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
}

.confirm-enter-active,
.confirm-leave-active {
    transition: opacity 160ms ease;
}

.confirm-enter-from,
.confirm-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .confirm-enter-active,
    .confirm-leave-active {
        transition: none;
    }
}
</style>
