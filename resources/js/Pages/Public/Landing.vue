<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Hero from '@/Components/sections/Hero.vue';
import LandingContact from '@/Components/sections/LandingContact.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';
import { provideLeadSource } from '@/Composables/useLeadSource';

/**
 * The single landing page (management decision, 7 September 2026).
 *
 * The whole site is one vertically scrolled document. What is on it, in what
 * order, and in which anchored group each block sits, all come from `sections`
 * rows — so Amad Craft can reorder or remove a block in the panel without a
 * developer, exactly as on every other page (§9.1). This file decides only
 * which component renders which type and where the anchors fall.
 *
 * The header is the site's own — the same SiteHeader every other page uses,
 * at the same size and weight. It carries the five menu entries as anchors
 * and scrolls to them here, because they are stored as `/{locale}#anchor` and
 * SiteHeader resolves that against the page being read. A second header for
 * this page meant two typographies for one site.
 *
 * Two types are rendered here rather than through SectionRenderer:
 *
 *   hero          — the shared, approved Hero: the same two panes, spine
 *                   lettering, eyebrow and film. It takes three audience
 *                   buttons instead of a primary/secondary pair, and the
 *                   brief's introduction sits in its copy pane beside the
 *                   film rather than in a band beneath it.
 *   contact_block — LandingContact is the one form on the site, and it needs
 *                   the segment the visitor arrived from.
 *
 * `provideLeadSource` is called once, here, because this page owns the form
 * every button on it points at.
 */
const { goToForm } = provideLeadSource();

const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

/**
 * The sections, cut into the anchored groups the header navigates between.
 *
 * Consecutive sections sharing an `anchor` become one `<section id="…">`, so
 * «الجهات» in the header lands on the first block of the government argument
 * and not on whichever single row happens to carry that id. A run with no
 * anchor still renders — it simply is not a destination.
 */
const groups = computed(() => {
    const out = [];

    for (const section of props.sections) {
        const anchor = section.settings?.anchor ?? null;
        const last = out[out.length - 1];

        if (last && last.anchor === anchor) {
            last.sections.push(section);
        } else {
            out.push({ anchor, sections: [section] });
        }
    }

    return out;
});

/**
 * The chapter number each group carries, and on which of its blocks.
 *
 * Six anchored groups, so the spine reads 01/06 … 06/06 rather than numbering
 * twenty-odd blocks into noise. It is shown on the group's first block that
 * can draw a margin — `rich_text` — because the hero has no margin column and
 * the contact block closes the page rather than continuing the argument.
 *
 * Derived, never written: reordering or removing a group in the panel
 * renumbers the rest instead of leaving a gap at "03 / 06".
 */
const INDEXABLE = 'rich_text';

const numbered = computed(() => {
    const marks = new Map();
    const groups_ = groups.value;

    groups_.forEach((group, i) => {
        const first = group.sections.find((section) => section.type === INDEXABLE);

        if (first) {
            marks.set(first.id, { index: i + 1, total: groups_.length });
        }
    });

    return marks;
});

/** `{ index, total }` for a block that opens a chapter, or an empty object. */
function chapter(section) {
    return numbered.value.get(section.id) ?? {};
}
</script>

<template>
    <!--
        `over-hero` keeps the gold hairline under the header.

        SiteHeader drops that rule on pages whose first block is not a
        full-bleed navy hero, because there the header's navy already meets a
        different ground and the line has nothing to separate. This page opens
        on exactly such a hero — the two navies meet — so without the flag the
        bar and the banner ran together as one field of colour.
    -->
    <PublicLayout :seo="seo" over-hero :previewing="previewing">
        <template v-for="(group, g) in groups" :key="group.anchor ?? g">
            <!--
                The Sadu thread as the divider between sections — §10.1's
                first of exactly three homes. Not before the first group:
                a rule above the hero divides it from the header, which is
                not a division that exists.
            -->
            <SaduDivider v-if="g > 0" />

            <section
                :id="group.anchor ?? undefined"
                class="anchor-group"
                :class="`anchor-group--${g % 2 === 0 ? 'paper' : 'sand'}`"
            >
                <template v-for="section in group.sections" :key="section.id">
                    <!--
                        The approved hero, unchanged: two real panes, the
                        eyebrow and spine lettering, and the workshop film in
                        the media pane. It takes three audience buttons here
                        instead of a primary/secondary pair — the only
                        difference from every other page that uses it.

                        Grouped in a <template> so the v-else-if chain below
                        still begins at the hero: with the lede as a sibling
                        v-if, a hero row fell through to the v-else and was
                        rendered a second time by SectionRenderer.
                    -->
                    <Hero
                        v-if="section.type === 'hero'"
                        :heading="section.heading ?? page?.title"
                        :subheading="section.subheading"
                        :body="section.body"
                        :settings="section.settings ?? {}"
                        :image="section.settings?.image"
                        :ctas="section.settings?.ctas ?? []"
                        display-sub
                        @segment="goToForm"
                    />

                    <LandingContact
                        v-else-if="section.type === 'contact_block'"
                        :heading="section.heading"
                        :body="section.body"
                        :settings="section.settings ?? {}"
                    />

                    <!-- Everything else goes through the shared renderer, so
                         there is one definition of how a section row becomes
                         component props. `data` carries the chapter number to
                         the block that opens one, by the renderer's own
                         per-type channel. -->
                    <SectionRenderer
                        v-else
                        :sections="[section]"
                        :data="{ [section.type]: chapter(section) }"
                    />
                </template>
            </section>
        </template>
    </PublicLayout>
</template>

<style scoped>
/*
 * Clears the sticky header when an anchor is jumped to — including when the
 * browser does it itself on a deep link like /ar#contact, which no JavaScript
 * is involved in. `scroll-margin-block-start`, not a physical offset.
 */
.anchor-group {
    scroll-margin-block-start: 80px;
}

/*
 * ALTERNATING GROUNDS
 *
 * Twenty-seven blocks on one flat ground read as a document, not as a
 * designed page — which is what a single landing page risks becoming when it
 * absorbs what used to be eleven. The old home page alternated navy, paper
 * and warm bands, and each navy `segment_cta` still punctuates the end of an
 * audience section. This restores the quieter half of that rhythm between
 * them: consecutive anchor groups sit on different grounds, so the eye is
 * told where one argument ends and the next begins.
 *
 * Two grounds only, both already in the palette. Not the Sadu ground —
 * §10.1 spends that once, behind the impact figures, and a page this long
 * would turn a signature into wallpaper.
 */
.anchor-group--paper {
    background: var(--paper-warm);
}

.anchor-group--sand {
    background: var(--sand);
}

/*
 * THE LISTS
 *
 * The approved copy leans hard on lists — seven things a solution must be,
 * nine problems a buyer meets, twelve skills a craft does not include. Set as
 * `list-style: disc` in a 68ch column, twelve short bullets stack into a
 * grey ladder that a procurement reader skims past, and that is the single
 * biggest reason this page looked plainer than the site it replaced.
 *
 * Set as a two-column table of hairline-separated rows, the same twelve items
 * read as a specification — which is what they are, and what the audience
 * this page is written for reads all day.
 */
.anchor-group :deep(.prose) {
    /* The measure moves from the column to the paragraph, so prose stays
       readable while the lists take the full width they need. */
    max-inline-size: none;
}

.anchor-group :deep(.prose__body > p) {
    max-inline-size: 68ch;
}

.anchor-group :deep(.prose__body ul) {
    display: grid;
    gap: 0 var(--s-7);
    margin-block: var(--s-5);
    padding-inline-start: 0;
    list-style: none;
}

.anchor-group :deep(.prose__body ul li) {
    position: relative;
    margin-block-end: 0;
    padding-block: var(--s-3);
    padding-inline-start: var(--s-5);
    border-block-start: 1px solid var(--hairline-soft);
    font-size: var(--fs-body);
}

/* A gold dash, not a bullet: §10.2 lists gold as the hairline and divider
   colour, and a rule reads as a specification where a dot reads as a note. */
.anchor-group :deep(.prose__body ul li::before) {
    content: "";
    position: absolute;
    inset-inline-start: 0;
    inset-block-start: calc(var(--s-3) + 0.7em);
    inline-size: 12px;
    block-size: 1px;
    background: var(--gold-400);
}

@media (min-width: 900px) {
    .anchor-group :deep(.prose__body ul) {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
