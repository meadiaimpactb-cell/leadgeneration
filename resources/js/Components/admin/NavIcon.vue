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
    /**
     * The set is shared with the public site, where the same glyphs are drawn
     * at 40px beside a service line. A larger icon needs a lighter stroke or
     * it reads as a filled shape, hence the two knobs — and `muted`, because
     * the sidebar wants the icon quieter than its label while the public page
     * wants it at full strength.
     */
    size: { type: Number, default: 18 },
    weight: { type: Number, default: 1.75 },
    muted: { type: Boolean, default: true },
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

    /* The screens added when the panel was regrouped. */
    media: 'M3 5h18v14H3V5zM3 15l5-5 4 4 3-3 6 6M8.5 9.5h.01',

    /*
     * The two actions on a chosen image. They are icons rather than words
     * because the words — «استبدال» and «إزالة من القسم» — wrapped to three
     * lines inside a 160px card and pushed the thumbnail out of shape.
     * Both carry an aria-label, so nothing is lost to a screen reader.
     */
    swap: 'M4 8h11l-3-3M20 16H9l3 3',
    trash: 'M4 7h16M10 7V5h4v2M6 7l1 13h10l1-13M10 11v6M14 11v6',
    crm: 'M4 7h6v6H4V7zM14 11h6v6h-6v-6zM10 10h4M12 10v4',
    bell: 'M18 8a6 6 0 10-12 0c0 7-3 8-3 8h18s-3-1-3-8M13.7 21a2 2 0 01-3.4 0',
    message: 'M20 15a2 2 0 01-2 2H8l-4 4V5a2 2 0 012-2h12a2 2 0 012 2v10z',
    shield: 'M12 3l8 4v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V7l8-4zM9 12l2 2 4-4',
    sitemap: 'M9 3h6v4H9V3zM3 17h6v4H3v-4zM15 17h6v4h-6v-4zM12 7v4M6 17v-2a1 1 0 011-1h10a1 1 0 011 1v2',
    activity: 'M3 12h4l3 8 4-16 3 8h4',
    languages: 'M4 6h10M9 4v2c0 5-2.5 8-5 9M7 11c1.5 3 4 5 6 5.5M13 20l4-9 4 9M14.8 17h4.4',
    backup: 'M21 12a9 9 0 11-3-6.7M21 4v5h-5',

    /* The four service lines (§4), drawn for the public page. */
    gifts: 'M3 9h18v12H3V9zM3 13h18M12 9v12M12 9c-3 0-5.3-1.2-5.3-3S8.9 4.1 10.1 5C11 5.6 12 7.2 12 9zM12 9c3 0 5.3-1.2 5.3-3S15.1 4.1 13.9 5C13 5.6 12 7.2 12 9z',
    events: 'M4 4h16v6H4V4zM4 10h16M8 14v6M16 14v6M12 14v6M6 20h12',
    production: 'M4 20l7-7M9 12l3-3M12 9l4-4 3 3-4 4zM16 5l-2-2',
    sourcing: 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 3c3 3.2 3 14.8 0 18M12 3c-3 3.2-3 14.8 0 18M3 12h18',

    dot: 'M12 12h.01',
};
</script>

<template>
    <!--
        `class` stays a plain static attribute. Adding a bound class here
        merges the two into `class="ico ico--muted"`, which silently breaks
        every check that looks for the attribute verbatim — so the muted state
        rides on an inline style instead.
    -->
    <svg
        class="ico"
        :style="muted ? undefined : { opacity: 1 }"
        viewBox="0 0 24 24"
        :width="size"
        :height="size"
        fill="none"
        stroke="currentColor"
        :stroke-width="weight"
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
    /* Slightly dimmer than the label so the word stays the primary signal.
       Overridden inline where the icon is the point rather than the label. */
    opacity: 0.75;
}
</style>
