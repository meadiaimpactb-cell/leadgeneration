import { reactive } from 'vue';

/**
 * Raising the panel's toast from a screen, not only from a redirect.
 *
 * Flash messages cover the ordinary path: the server saves, redirects, and
 * `AdminLayout` shows what it said. They cannot cover the path that actually
 * confused the client — a save REJECTED by validation. That response carries
 * errors rather than a flash, so nothing was said at all: press save, and the
 * screen goes quiet in a way indistinguishable from success.
 *
 * `bump` is what AdminLayout watches. The counter is the signal rather than
 * the text, because the same message twice in a row is still two events, and
 * the second is exactly when the editor needs telling.
 */
export const notice = reactive({
    bump: 0,
    type: 'error',
    text: null,
});

function raise(type, text) {
    if (!text) return;

    notice.type = type;
    notice.text = text;
    notice.bump += 1;
}

export function notifyError(text) {
    raise('error', text);
}

export function notifySuccess(text) {
    raise('success', text);
}
