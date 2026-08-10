<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';
import Hero from '@/Components/sections/Hero.vue';
import IntroStatement from '@/Components/sections/IntroStatement.vue';
import SolutionsGrid from '@/Components/sections/SolutionsGrid.vue';
import SectorSpotlight from '@/Components/sections/SectorSpotlight.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import StoryCarousel from '@/Components/sections/StoryCarousel.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';

/**
 * The home page (§11.1).
 *
 * Order and presence of blocks come from `sections` rows, not from this file,
 * so the client can reorder or remove any of them from the admin panel
 * without a developer. This component only decides WHICH Vue component
 * renders a given section type, and where the two permitted Sadu dividers go.
 */
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

const hero = computed(() => section('hero'));
const intro = computed(() => section('intro_statement'));
const solutionsCopy = computed(() => section('solutions_grid'));
const sectorsCopy = computed(() => section('sector_spotlight'));
const impactCopy = computed(() => section('stats'));
const storiesCopy = computed(() => section('story_carousel'));
const partnersCopy = computed(() => section('logos'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <!-- The only page whose hero sits under a transparent header (§11.1). -->
    <PublicLayout :seo="seo" over-hero :previewing="previewing">
        <Hero
            :heading="hero.heading ?? page?.title"
            :subheading="hero.subheading ?? page?.subtitle"
            :cta-label="hero.ctaLabel"
            :cta-url="hero.ctaUrl"
            :secondary-label="hero.settings?.secondaryLabel"
            :secondary-url="hero.settings?.secondaryUrl"
            :image="hero.settings?.image"
        />

        <!-- Permitted Sadu use 1 of 3: the divider between sections (§10.1). -->
        <SaduDivider />

        <IntroStatement :body="intro.body" />

        <SolutionsGrid :heading="solutionsCopy.heading" :items="solutions" />

        <SectorSpotlight :heading="sectorsCopy.heading" :items="sectors" />

        <!-- Permitted Sadu use 2 of 3: the faint ground behind the numbers. -->
        <ImpactStats
            :heading="impactCopy.heading"
            :items="impact"
            :cta-label="impactCopy.ctaLabel"
            :cta-url="impactCopy.ctaUrl"
        />

        <StoryCarousel :heading="storiesCopy.heading" :items="stories" />

        <PartnersLogos :heading="partnersCopy.heading" :items="partners" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
