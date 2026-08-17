import { reactive } from 'vue';

/**
 * The panel's confirmation prompt, as a promise.
 *
 * Eight screens asked "are you sure?" with the browser's own `confirm()`. That
 * dialog cannot be styled, cannot be read in the panel's voice, names its
 * buttons "OK" and "Cancel" in the browser's language rather than the
 * editor's, and — the part that matters on an RTL panel — lays them out to the
 * browser's rules, not the document's. It also blocks the main thread, so the
 * screen behind it freezes mid-interaction.
 *
 * State lives at module scope with a single dialog mounted in AdminLayout,
 * rather than a dialog per screen. A call site should be able to ask a
 * question from an event handler without also having to render something.
 *
 * Deliberately not SweetAlert2. `ui/Toast.vue` records the same decision for
 * the same reason: what would remain after stripping its type scale, radius,
 * palette and icons is a promise and a transition, which is this file and its
 * component. The RTL workarounds a wrapper needs — picking `top-start` over
 * `top-end` by direction, `reverseButtons`, re-reading `dir` on every call —
 * are all symptoms of a library that lays out left-to-right first. Here the
 * document's own direction does the work and there is nothing to reverse.
 */
export const state = reactive({
    open: false,
    title: null,
    message: null,
    confirmLabel: null,
    resolve: null,
});

/**
 * Ask, and wait for the answer.
 *
 * @returns {Promise<boolean>} true if confirmed, false if dismissed.
 */
export function confirmDialog({ title = null, message = null, confirmLabel = null } = {}) {
    // A second question while one is open answers the first with "no". The
    // alternative is a queue of stale prompts about work the editor has
    // already moved on from.
    settle(false);

    state.title = title;
    state.message = message;
    state.confirmLabel = confirmLabel;
    state.open = true;

    return new Promise((resolve) => {
        state.resolve = resolve;
    });
}

/** Close the dialog and hand the answer back to whoever asked. */
export function settle(answer) {
    const resolve = state.resolve;

    state.open = false;
    state.resolve = null;

    if (resolve) {
        resolve(answer);
    }
}
