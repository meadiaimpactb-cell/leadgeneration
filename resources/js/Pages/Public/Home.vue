<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import NewsTicker from '@/Components/sections/NewsTicker.vue';
import Hero from '@/Components/sections/Hero.vue';
import IntroStatement from '@/Components/sections/IntroStatement.vue';
import SolutionsGrid from '@/Components/sections/SolutionsGrid.vue';
import Gallery from '@/Components/sections/Gallery.vue';
import SectorSpotlight from '@/Components/sections/SectorSpotlight.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import StoryCarousel from '@/Components/sections/StoryCarousel.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The home page (§11.1).
 *
 * Order and presence of blocks come from `sections` rows, not from this file,
 * so the client can reorder or remove any of them from the admin panel
 * without a developer. This component only decides WHICH Vue component
 * renders a given section type, and how the running index is counted.
 */
const { t } = useTranslation();

const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    solutions: { type: Array, default: () => [] },
    sectors: { type: Array, default: () => [] },
    impact: { type: Array, default: () => [] },
    stories: { type: Array, default: () => [] },
    partners: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

/** Copy for one section type, or an empty object if the client has not added it. */
function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

/**
 * The blocks that carry a running number, in the order they appear.
 *
 * Counted from the sections the client actually has, so deleting one in the
 * admin panel renumbers the rest instead of leaving a gap at "03 / 06". The
 * hero, the figures and the closing CTA are excluded on purpose — they are
 * the covers of the document, not chapters in it.
 */
const NUMBERED = ['intro_statement', 'solutions_grid', 'gallery', 'sector_spotlight', 'story_carousel'];

const numbered = computed(() => NUMBERED.filter((type) => props.sections.some((s) => s.type === type)));

/** 1-based position of a section among the numbered ones, or null. */
function counter(type) {
    const i = numbered.value.indexOf(type);

    return i === -1 ? null : i + 1;
}

const total = computed(() => numbered.value.length);

/**
 * The caption beside the running number — "01 / 05 · MANIFESTO".
 *
 * These five words used to be typed into the template below, which made them
 * the only thing on the home page that did not follow its section: reorder
 * the blocks in the panel and the numbers renumbered while the captions
 * stayed where they were. SectionIndex's own docblock already claimed they
 * came from the section; they did not.
 *
 * Two sources now, in order:
 *   1. the section's own `index_label` setting, if the client has written one
 *      — the same JSON settings block that already carries `eyebrow`;
 *   2. otherwise the shipped label for that section TYPE, so a section added
 *      tomorrow is captioned without anyone editing this file.
 *
 * Neither is written here, and an unknown type returns null rather than
 * printing its own key at a visitor (§22.1). The words themselves are
 * unchanged — the design is approved; only where they come from has moved.
 */
function caption(type) {
    const written = section(type)?.settings?.index_label;

    if (typeof written === 'string' && written.trim() !== '') {
        return written.trim();
    }

    const key = `common.section_label_${type}`;
    const label = t(key);

    return label === key ? null : label;
}

const ticker = computed(() => section('news_ticker'));
const hero = computed(() => section('hero'));
const intro = computed(() => section('intro_statement'));
const solutionsCopy = computed(() => section('solutions_grid'));
const galleryCopy = computed(() => section('gallery'));
const sectorsCopy = computed(() => section('sector_spotlight'));
const storiesCopy = computed(() => section('story_carousel'));
const partnersCopy = computed(() => section('logos'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <!-- The only page whose hero sits under a transparent header (§11.1). -->
    <PublicLayout :seo="seo" over-hero :previewing="previewing">
        <template #ticker>
            <NewsTicker
                :heading="ticker.heading"
                :body="ticker.body"
                :cta-label="ticker.ctaLabel"
                :cta-url="ticker.ctaUrl"
            />
        </template>

        <Hero
            :heading="hero.heading ?? page?.title"
            :subheading="hero.subheading ?? page?.subtitle"
            :eyebrow="hero.settings?.eyebrow"
            :cta-label="hero.ctaLabel"
            :cta-url="hero.ctaUrl"
            :secondary-label="hero.settings?.secondaryLabel"
            :secondary-url="hero.settings?.secondaryUrl"
            :image="hero.settings?.image"
        />

        <!--
            The proof figures, riding up over the hero's lower edge. Placed
            here rather than as a section of their own because the overlap is
            what ties the claim to the evidence.
        -->
        <ImpactStats variant="overlap" :items="impact" />

        <IntroStatement
            :body="intro.body"
            :index="counter('intro_statement')"
            :total="total"
            :slug="caption('intro_statement')"
        />

        <SolutionsGrid
            :heading="solutionsCopy.heading"
            :eyebrow="solutionsCopy.settings?.eyebrow"
            :note="solutionsCopy.subheading"
            :items="solutions"
            :index="counter('solutions_grid')"
            :total="total"
            :slug="caption('solutions_grid')"
        />

        <Gallery
            :heading="galleryCopy.heading"
            :eyebrow="galleryCopy.settings?.eyebrow"
            :images="galleryCopy.settings?.images ?? []"
            :cta-label="galleryCopy.ctaLabel"
            :cta-url="galleryCopy.ctaUrl"
            :index="counter('gallery')"
            :total="total"
            :slug="caption('gallery')"
        />

        <SectorSpotlight
            :heading="sectorsCopy.heading"
            :eyebrow="sectorsCopy.settings?.eyebrow"
            :items="sectors"
            :index="counter('sector_spotlight')"
            :total="total"
            :slug="caption('sector_spotlight')"
        />

        <StoryCarousel
            :heading="storiesCopy.heading"
            :eyebrow="storiesCopy.settings?.eyebrow"
            :items="stories"
            :index="counter('story_carousel')"
            :total="total"
            :slug="caption('story_carousel')"
        />

        <PartnersLogos :heading="partnersCopy.heading" :items="partners" />

        <CtaBand
            :heading="ctaCopy.heading"
            :eyebrow="ctaCopy.settings?.eyebrow"
            :reassurance="ctaCopy.subheading"
            :note="ctaCopy.body"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
