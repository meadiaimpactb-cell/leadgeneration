<script setup>
/**
 * ui/FlagIcon — the two flags this site needs, drawn inline.
 *
 * Not emoji: Windows ships no flag glyphs at all, so 🇸🇦 renders as the bare
 * letters "SA" on the machine this panel is used from. Not a flag package
 * either — those carry two hundred and fifty SVGs to deliver two.
 *
 * Both are the simplified renditions an icon set uses at 24px: at this size
 * the Saudi shahada is drawn as the calligraphic band it reads as rather than
 * as letters, and the American canton carries a suggestion of stars rather
 * than fifty of them. They are identifiers in a language switch, not
 * reproductions.
 */
defineProps({
    /** `sa` or `us`. */
    country: { type: String, required: true },
    width: { type: Number, default: 20 },
});
</script>

<template>
    <!-- Never mirrored: §12 flips directional icons, and a flag is not one. -->
    <svg
        v-if="country === 'sa'"
        class="flag"
        :width="width"
        :height="(width * 2) / 3"
        viewBox="0 0 24 16"
        role="img"
        aria-hidden="true"
    >
        <rect width="24" height="16" rx="2" fill="#006C35" />
        <!-- The shahada band. -->
        <path
            d="M5 6.2h14"
            stroke="#fff"
            stroke-width="1.6"
            stroke-linecap="round"
            opacity=".95"
        />
        <!-- The sword, blade and hilt. -->
        <path d="M5 10.4h12.2" stroke="#fff" stroke-width="1.1" stroke-linecap="round" />
        <path d="M17.2 9.3v2.2" stroke="#fff" stroke-width="1.1" stroke-linecap="round" />
    </svg>

    <svg
        v-else
        class="flag"
        :width="width"
        :height="(width * 2) / 3"
        viewBox="0 0 24 16"
        role="img"
        aria-hidden="true"
    >
        <rect width="24" height="16" rx="2" fill="#fff" />
        <g fill="#B22234">
            <rect y="0" width="24" height="1.85" />
            <rect y="3.7" width="24" height="1.85" />
            <rect y="7.4" width="24" height="1.85" />
            <rect y="11.1" width="24" height="1.85" />
            <rect y="14.8" width="24" height="1.2" />
        </g>
        <rect width="10" height="8.6" fill="#3C3B6E" />
        <g fill="#fff">
            <circle cx="2" cy="2" r=".6" />
            <circle cx="5" cy="2" r=".6" />
            <circle cx="8" cy="2" r=".6" />
            <circle cx="3.5" cy="4.3" r=".6" />
            <circle cx="6.5" cy="4.3" r=".6" />
            <circle cx="2" cy="6.6" r=".6" />
            <circle cx="5" cy="6.6" r=".6" />
            <circle cx="8" cy="6.6" r=".6" />
        </g>
    </svg>
</template>

<style scoped>
.flag {
    display: block;
    flex: 0 0 auto;
    border-radius: 2px;
    /* A white flag on a white header would otherwise have no edge. */
    box-shadow: 0 0 0 1px rgba(0, 37, 70, 0.15);
}
</style>
