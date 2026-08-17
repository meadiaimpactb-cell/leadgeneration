<script setup>
/**
 * ui/SocialLinks — the client's social accounts as marks, not as words.
 *
 * Extracted from `SiteFooter`, which owned this glyph table alone. The contact
 * card printed the same accounts as plain text links reading "Snapchat
 * Facebook X Instagram" — four words where the footer three sections below
 * showed four icons, on the same screen. One table, two readers.
 *
 * The glyph is matched on the link's own host, so a client who pastes an
 * Instagram URL gets the Instagram mark without being asked to pick an icon,
 * and an unrecognised network falls back to a globe — never a wrong brand mark
 * and never its name printed inside a 44px square.
 */
defineProps({
    /** `[{label, url}]` from the contact settings. */
    items: { type: Array, default: () => [] },
    /**
     * `dark` for a navy ground, `paper` for a light one. Only the colours
     * differ; the geometry is the same in both so the two never look like
     * different components.
     */
    tone: {
        type: String,
        default: 'dark',
        validator: (v) => ['dark', 'paper'].includes(v),
    },
    label: { type: String, default: null },
});

const MARKS = {
    instagram: 'M3 8a5 5 0 015-5h8a5 5 0 015 5v8a5 5 0 01-5 5H8a5 5 0 01-5-5V8zM12 16a4 4 0 100-8 4 4 0 000 8zM17.5 6.5h.01',
    x: 'M4 4l16 16M20 4L4 20',
    twitter: 'M4 4l16 16M20 4L4 20',
    linkedin: 'M3 3h18v18H3V3zM7 10v7M7 7v.01M11 17v-4a2.5 2.5 0 015 0v4M11 10v7',
    youtube: 'M2 8a3 3 0 013-3h14a3 3 0 013 3v8a3 3 0 01-3 3H5a3 3 0 01-3-3V8zM10 9l5 3-5 3V9z',
    facebook: 'M14 8h3V4h-3a4 4 0 00-4 4v2H8v4h2v6h4v-6h3l1-4h-4V8z',
    snapchat: 'M12 3c2.5 0 4 1.9 4 4.3 0 1 .1 1.9-.1 2.5.5.3 1.2 0 1.6-.2.5.7-.3 1.5-1.3 1.9.5 1.7 2 3 3.3 3.3.3.6-1 1.2-2.4 1.4-.2.4-.1 1.1-.6 1.2-.7.1-1.6-.4-2.7 0-.9.3-1.5 1.3-2.8 1.3s-1.9-1-2.8-1.3c-1.1-.4-2 .1-2.7 0-.5-.1-.4-.8-.6-1.2-1.4-.2-2.7-.8-2.4-1.4 1.3-.3 2.8-1.6 3.3-3.3-1-.4-1.8-1.2-1.3-1.9.4.2 1.1.5 1.6.2-.2-.6-.1-1.5-.1-2.5C8 4.9 9.5 3 12 3z',
    default: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3a14 14 0 010 18 14 14 0 010-18z',
};

function mark(url) {
    const host = String(url ?? '').toLowerCase();

    for (const key of Object.keys(MARKS)) {
        if (key !== 'default' && host.includes(key)) return MARKS[key];
    }

    return MARKS.default;
}
</script>

<template>
    <ul v-if="items.length" class="social" :class="`social--${tone}`" :aria-label="label ?? undefined">
        <li v-for="channel in items" :key="channel.url">
            <a
                class="social__mark"
                :href="channel.url"
                rel="noopener noreferrer"
                target="_blank"
                :aria-label="channel.label"
            >
                <svg
                    viewBox="0 0 24 24"
                    width="17"
                    height="17"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path :d="mark(channel.url)" />
                </svg>
            </a>
        </li>
    </ul>
</template>

<style scoped>
.social {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    list-style: none;
    margin: 0;
    padding: 0;
}

/* 44px: the minimum touch target, which is also the size that makes a row of
   four read as a set of controls rather than as decoration. */
.social__mark {
    display: grid;
    place-items: center;
    inline-size: 44px;
    block-size: 44px;
    border: 1px solid var(--social-line);
    color: var(--social-ink);
    transition: color var(--dur-micro) var(--ease), border-color var(--dur-micro) var(--ease),
        background-color var(--dur-micro) var(--ease);
}

.social__mark:hover,
.social__mark:focus-visible {
    border-color: var(--gold-400);
    color: var(--gold-400);
    background: var(--social-hover);
}

/*
 * Gold on navy, which is the treatment the footer already used and therefore
 * the one the identity has settled on for these. Taking the footer's look as
 * the shared default rather than inventing a third is the point of extracting
 * the component at all.
 */
.social--dark {
    --social-line: rgba(220, 173, 117, 0.4);
    --social-ink: var(--gold-400);
    --social-hover: rgba(220, 173, 117, 0.16);
}

.social--dark .social__mark:hover,
.social--dark .social__mark:focus-visible {
    color: #fff;
}

.social--paper {
    --social-line: var(--hairline);
    --social-ink: var(--navy-900);
    --social-hover: var(--gold-100);
}

@media (prefers-reduced-motion: reduce) {
    .social__mark {
        transition: none;
    }
}
</style>
