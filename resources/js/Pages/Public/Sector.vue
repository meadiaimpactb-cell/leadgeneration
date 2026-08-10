<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import SolutionsGrid from '@/Components/sections/SolutionsGrid.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

/**
 * A sector page (§11.2).
 *
 * The lead field at the bottom carries `sectorHint`, so the sales team can
 * see which segment a prospect came from without ever having asked them (§6.1).
 */
const props = defineProps({
    sector: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    solutions: { type: Array, default: () => [] },
    impact: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const solutionsCopy = computed(() => section('solutions_grid'));
const impactCopy = computed(() => section('stats'));
const clientsCopy = computed(() => section('logos'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <PublicLayout :seo="seo">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero
            :title="sector.name"
            :subtitle="sector.summary"
            :image="sector.image"
        />

        <SaduDivider />

        <!-- Whatever the client composed: cards, media split, FAQ, quote… -->
        <SectionRenderer :sections="sections" :skip="['cta_band']" />

        <SolutionsGrid :heading="solutionsCopy.heading" :items="solutions" />

        <ImpactStats :heading="impactCopy.heading" :items="impact" />

        <PartnersLogos :heading="clientsCopy.heading" :items="clients" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
            :sector-hint="sector.key"
        />
    </PublicLayout>
</template>
