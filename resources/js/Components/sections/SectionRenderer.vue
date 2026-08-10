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

/**
 * Renders whatever the client composed in the section builder (§9.1).
 *
 * This is the piece that makes "add, reorder and remove sections without a
 * developer" true: a page hands its `sections` array here and the right Vue
 * component is chosen per row.
 *
 * Types tied to a specific dataset — solutions_grid, stats, story_carousel —
 * are rendered by the page itself, because only the page has that data.
 */
const props = defineProps({
    sections: { type: Array, default: () => [] },
    // Section types the parent page renders itself; skipped here.
    skip: { type: Array, default: () => [] },
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
};

const renderable = computed(() =>
    props.sections.filter((s) => COMPONENTS[s.type] && !props.skip.includes(s.type))
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
    return { ...base, ...(section.settings ?? {}) };
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
