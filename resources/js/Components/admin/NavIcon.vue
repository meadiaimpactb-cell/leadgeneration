<script setup>
/**
 * One line-art glyph per navigation entry.
 *
 * Icons exist here for recognition, not decoration. A sidebar of twenty-odd
 * Arabic labels is read letter by letter every time; a shape beside each one
 * is found by memory after the second visit, which is the difference between
 * a panel a non-technical person tolerates and one they navigate.
 *
 * Drawn in `currentColor` on a 24-grid so they inherit the link's colour and
 * its active state, and stay one weight across the whole panel. No icon font,
 * no sprite request — a handful of paths costs less than either.
 */
defineProps({
    name: { type: String, required: true },
});

/*
 * Keyed by the same identifier the nav item carries. An unknown key falls
 * through to a neutral dot rather than leaving a hole, so adding a screen can
 * never break the alignment of the ones around it.
 */
const PATHS = {
    dashboard: 'M4 13h6V4H4v9zm0 7h6v-5H4v5zm10 0h6V11h-6v9zm0-16v5h6V4h-6z',
    leads: 'M17 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9.5 10a4 4 0 100-8 4 4 0 000 8zM22 20v-2a4 4 0 00-3-3.9',
    pages: 'M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h6',
    solutions: 'M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.2l5.9-.8L12 3z',
    sectors: 'M3 21h18M5 21V8l7-5 7 5v13M10 21v-6h4v6',
    products: 'M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7M12 11v10',
    categories: 'M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z',
    impact: 'M3 20h18M6 20V10M11 20V4M16 20v-7M21 20v-4',
    stories: 'M20 15a2 2 0 01-2 2H8l-4 4V5a2 2 0 012-2h12a2 2 0 012 2v10z',
    reports: 'M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 15l2 2 4-4',
    training: 'M22 9L12 4 2 9l10 5 10-5zM6 11.5V17c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.5',
    partners: 'M8 11a3.5 3.5 0 100-7 3.5 3.5 0 000 7zm8 0a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM2 20a6 6 0 0112 0M12 20a6 6 0 0110 0',
    campaigns: 'M3 11v2a1 1 0 001 1h2l4 4V6L6 10H4a1 1 0 00-1 1zM15 8.5a4 4 0 010 7M18 5.5a8 8 0 010 13',
    fields: 'M4 6h16M4 12h10M4 18h7',
    navigation: 'M4 6h16M4 12h16M4 18h16',
    redirects: 'M4 8h11l-3-3M20 16H9l3 3',
    users: 'M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 10a4 4 0 100-8 4 4 0 000 8zM22 20v-2a4 4 0 00-3-3.9M16 2.1a4 4 0 010 7.8',
    contact: 'M6.5 3h3l1.5 4-2 1.5a12 12 0 006.5 6.5L17 13l4 1.5v3a2 2 0 01-2.2 2A17 17 0 013.1 5.2 2 2 0 015 3h1.5z',
    site: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3a14 14 0 010 18 14 14 0 010-18z',
    brand: 'M12 3l7 4v6c0 4-3 7-7 8-4-1-7-4-7-8V7l7-4zM9.5 12l1.8 1.8 3.2-3.6',
    store: 'M4 7h16l-1 4H5L4 7zM5 11v8h14v-8M9 19v-4h6v4',
    seo: 'M11 18a7 7 0 100-14 7 7 0 000 14zM21 21l-5-5',
    keywords: 'M15 7a4 4 0 11-4 4l-7 7v3h3l7-7a4 4 0 011-7z',
    robots: 'M8 3v3M16 3v3M5 6h14a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zM9 12h.01M15 12h.01M9 16h6',
    tracking: 'M3 17l5-6 4 3 4-6 5 4M3 21h18',
    advanced: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.6 1.6 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.6 1.6 0 00-2.7 1.1v.2a2 2 0 11-4 0V21a1.6 1.6 0 00-2.7-1.1l-.1.1a2 2 0 11-2.8-2.8l.1-.1A1.6 1.6 0 003 15a2 2 0 010-4 1.6 1.6 0 001.1-2.7l-.1-.1a2 2 0 112.8-2.8l.1.1A1.6 1.6 0 0010 4.6V4a2 2 0 014 0v.2a1.6 1.6 0 002.7 1.1l.1-.1a2 2 0 112.8 2.8l-.1.1A1.6 1.6 0 0021 11a2 2 0 010 4z',
    profile: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
    dot: 'M12 12h.01',
};
</script>

<template>
    <svg
        class="ico"
        viewBox="0 0 24 24"
        width="18"
        height="18"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
    >
        <path :d="PATHS[name] ?? PATHS.dot" />
    </svg>
</template>

<style scoped>
.ico {
    flex: 0 0 auto;
    /* Slightly dimmer than the label so the word stays the primary signal. */
    opacity: 0.75;
}
</style>
