<script setup>
import Logo from '@/Components/ui/Logo.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * ui/ReportCover — what a published report looks like before it is opened.
 *
 * A report is a document, and a card that shows a photograph in the space a
 * cover belongs reads as a product for sale. That is exactly what was here:
 * the same stock photograph of a necklace on both annual reports, from outside
 * the identity, on the one page whose whole job is to be credible.
 *
 * Two sources, in order:
 *
 *  1. A cover the client uploaded. Theirs, so it wins — a real report has a
 *     designed cover and nothing generated will beat it.
 *  2. A generated face in the identity: navy ground, the mark, the year in the
 *     display face, a Sadu hairline at the foot. Built from tokens and markup,
 *     so it costs no bytes, is never the wrong year, and never looks like
 *     someone else's photograph.
 *
 * §22.1 note — the brief asks for the first page of the PDF as a thumbnail.
 * That needs Imagick, which is not installed here (`extension_loaded('imagick')`
 * is false), so the generated face is what ships. If Imagick is added, a
 * medialibrary conversion on the `file` collection can populate `cover`
 * automatically and this component needs no change: an uploaded or generated
 * cover already wins over nothing.
 */
defineProps({
    /** An uploaded cover: `{url, webp, alt, width, height}`. */
    cover: { type: Object, default: null },
    /** Drives the generated face. Never typed twice — it is the year field. */
    year: { type: [Number, String], default: null },
});

const { t } = useTranslation();
</script>

<template>
    <img
        v-if="cover"
        class="rcover rcover--photo"
        :src="cover.webp ?? cover.url"
        :alt="cover.alt ?? ''"
        :width="cover.width ?? undefined"
        :height="cover.height ?? undefined"
        loading="lazy"
        decoding="async"
    />

    <!--
        Decorative: the year and the title are read out beside this card as
        real text, so a screen reader announcing the face again would repeat
        them. `aria-hidden` rather than a label.
    -->
    <span v-else class="rcover rcover--made on-dark" aria-hidden="true">
        <Logo class="rcover__mark" lockup="stacked" tone="white" />

        <span v-if="year" class="rcover__year tabular">{{ year }}</span>

        <!--
            No Sadu here. The vocabulary is a closed set of seven placements
            (DESIGN_SYSTEM.md) and a report cover is not one of them; §10.1's
            rule is that boldness is spent once. The gold rule and the mark
            carry the document read without spending it again.
        -->
        <span class="rcover__kind">{{ t('impact.report_kind') }}</span>
    </span>
</template>

<style scoped>
.rcover {
    display: block;
    inline-size: 100%;
    aspect-ratio: 3 / 4;
    border-radius: var(--r-sm);
}

.rcover--photo {
    object-fit: cover;
}

.rcover--made {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--s-3);
    overflow: hidden;
    background: var(--navy-900);
    /*
     * The inner rule is what makes this read as a printed cover rather than a
     * coloured rectangle: a document has a margin, and the eye knows it.
     */
    box-shadow: inset 0 0 0 1px rgb(var(--gold-rgb) / 0.28),
        inset 0 0 0 var(--s-3) var(--navy-900),
        inset 0 0 0 calc(var(--s-3) + 1px) rgb(var(--gold-rgb) / 0.42);
}

.rcover__mark {
    inline-size: 84px;
    opacity: 0.92;
}

.rcover__year {
    font-family: var(--font-display);
    font-size: clamp(1.75rem, 5vw, 2.5rem);
    font-weight: 700;
    line-height: 1;
    color: var(--gold-400);
    /* The year is a number in both locales; it must not mirror. */
    direction: ltr;
}

.rcover__kind {
    font-family: var(--font-mono);
    font-size: 0.625rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.55);
    direction: ltr;
}
</style>
