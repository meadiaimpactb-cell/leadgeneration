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

        <!--
            The shahada, as calligraphy reads at 20px rather than as letters.
            It was a single straight bar, which is not what is on the flag —
            the inscription is a dense cursive band with ascenders rising from
            it, and that shape is what makes it recognisable at this size.
        -->
        <g stroke="#fff" fill="none" stroke-linecap="round">
            <path d="M4.8 6.9c.7-1.1 1.4-1.1 2.1 0s1.4 1.1 2.1 0 1.4-1.1 2.1 0 1.4 1.1 2.1 0 1.4-1.1 2.1 0 1.4 1.1 2.1 0"
                  stroke-width="1.15" />
            <path d="M6.6 5.1v1.4M9.4 4.6v1.9M12.2 4.9v1.6M15 4.6v1.9M17.8 5.1v1.4"
                  stroke-width=".85" />
        </g>

        <!--
            The sword: blade to the left, hilt and pommel to the right, which
            is how it sits on the flag.
        -->
        <g fill="#fff">
            <path d="M5.4 10.9 15.6 10.25v1.3z" />
            <rect x="15.6" y="9.85" width=".85" height="2.1" rx=".4" />
            <circle cx="17.5" cy="10.9" r=".95" />
        </g>
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
