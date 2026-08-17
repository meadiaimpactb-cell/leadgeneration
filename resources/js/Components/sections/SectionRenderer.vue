<script setup>
import { computed } from 'vue';
import RichText from '@/Components/sections/RichText.vue';
import MediaSplit from '@/Components/sections/MediaSplit.vue';
import IntroStatement from '@/Components/sections/IntroStatement.vue';
import CardsGrid from '@/Components/sections/CardsGrid.vue';
import FaqAccordion from '@/Components/sections/FaqAccordion.vue';
import VideoBlock from '@/Components/sections/VideoBlock.vue';
import Gallery from '@/Components/sections/Gallery.vue';
import Timeline from '@/Components/sections/Timeline.vue';
import Testimonial from '@/Components/sections/Testimonial.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import BridgeModel from '@/Components/sections/BridgeModel.vue';
import TeamGrid from '@/Components/sections/TeamGrid.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';

/**
 * Renders whatever the client composed in the section builder (§9.1).
 *
 * This is the piece that makes "add, reorder and remove sections without a
 * developer" true: a page hands its `sections` array here and the right Vue
 * component is chosen per row.
 *
 * Types tied to a specific dataset — solutions_grid, story_carousel — are
 * rendered by the page itself, because only the page has that data.
 *
 * `data` is the exception that keeps the panel in charge of ORDER. A page can
 * hand a dataset here keyed by section type, and the matching section renders
 * in its configured position instead of being lifted out of the sequence. It
 * is what lets /about put the figures between the milestones and the
 * accreditations without a developer deciding that — while still calling the
 * same ImpactStats the home page calls, on the same records.
 */
const props = defineProps({
    sections: { type: Array, default: () => [] },
    // Section types the parent page renders itself; skipped here.
    skip: { type: Array, default: () => [] },
    /**
     * Restrict this pass to these types, in the client's own order.
     *
     * A page sometimes needs its composed sections in two places rather than
     * one — the segment pages put cards before the timeline and questions
     * after the figures, because a visitor should meet the objections after
     * the answers, not before them. Empty means no restriction, which is
     * every existing use.
     */
    only: { type: Array, default: () => [] },
    /**
     * `{ stats: { items, measuredAt }, logos: { items } }` — props a page
     * supplies for a section type, keyed by that type. Props rather than a
     * bare list, because a dataset usually arrives with something derived
     * beside it, and the figures' measurement date is not optional decoration
     * on a page a public body will quote.
     */
    data: { type: Object, default: () => ({}) },
});

const COMPONENTS = {
    rich_text: RichText,
    intro_statement: IntroStatement,
    media_split: MediaSplit,
    cards: CardsGrid,
    accordion: FaqAccordion,
    video: VideoBlock,
    gallery: Gallery,
    timeline: Timeline,
    testimonial: Testimonial,
    cta_band: CtaBand,
    bridge_model: BridgeModel,
    team: TeamGrid,
    stats: ImpactStats,
    logos: PartnersLogos,
};

const renderable = computed(() =>
    props.sections.filter(
        (s) =>
            COMPONENTS[s.type] &&
            !props.skip.includes(s.type) &&
            (props.only.length === 0 || props.only.includes(s.type))
    )
);

/** Map a section row onto the props its component expects. */
function propsFor(section) {
    const base = {
        heading: section.heading,
        subheading: section.subheading,
        body: section.body,
        ctaLabel: section.ctaLabel,
        ctaUrl: section.ctaUrl,
    };

    // `settings` carries the non-translatable options the admin panel set —
    // image, alignment, items — so it wins over the defaults above.
    // A dataset the page supplied for this type wins over both: the figures
    // are rows in a table, never something typed into a section's settings.
    return { ...base, ...(section.settings ?? {}), ...(props.data[section.type] ?? {}) };
}
</script>

<template>
    <component
        :is="COMPONENTS[section.type]"
        v-for="section in renderable"
        :key="section.id"
        v-bind="propsFor(section)"
    />
</template>
