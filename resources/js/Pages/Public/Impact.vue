<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import StoryCarousel from '@/Components/sections/StoryCarousel.vue';
import ReportsList from '@/Components/sections/ReportsList.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    impact: { type: Array, default: () => [] },
    measuredAt: { type: String, default: null },
    stories: { type: Array, default: () => [] },
    storiesTotal: { type: Number, default: 0 },
    storiesUrl: { type: String, default: null },
    reports: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

/*
 * `inertia`, NOT `page`.
 *
 * This page has a prop called `page`, and in <script setup> a top-level
 * binding of the same name wins in the template. Naming the usePage() handle
 * `page` silently replaced the prop, so `page?.title` resolved to the Inertia
 * page object — which has no title — and the hero rendered as two buttons on
 * an empty navy field. Nothing threw; the heading simply stopped existing.
 */
const inertia = usePage();

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const heroCopy = computed(() => section('hero'));

/**
 * `settings` is one JSON column shared by both locales, so the English label
 * is a separate key rather than a separate row. No Arabic fallback (§12): an
 * English visitor sees the second action only if it was written for them.
 */
const secondaryLabel = computed(() => {
    const settings = heroCopy.value.settings ?? {};

    return inertia.props.locale === 'en' ? settings.secondaryLabel_en : settings.secondaryLabel;
});

const statsCopy = computed(() => section('stats'));
const storiesCopy = computed(() => section('story_carousel'));
const reportsCopy = computed(() => section('reports_list'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero
            :title="page?.title"
            :subtitle="page?.subtitle"
            :cta-label="heroCopy.ctaLabel"
            :cta-url="heroCopy.settings?.ctaUrl"
            :secondary-label="secondaryLabel"
            :secondary-url="heroCopy.settings?.secondaryUrl"
        />

        <SaduDivider />

        <!--
            The same component the home page calls, in its `overlap` form —
            not a second copy of it.

            It was rendering as `band`: a navy section directly beneath a navy
            hero, so the two ran together into one unbroken field and the
            figures read as part of the header rather than as evidence. The
            floating card breaks the run and ties the numbers to the claim
            above them. See the rhythm rule in DESIGN_SYSTEM.md.

            No heading. «أثرنا بالأرقام» over four large numbers announces what
            the numbers already say; the card defines itself.
        -->
        <ImpactStats variant="overlap" :items="impact" :measured-at="measuredAt" />

        <!--
            Everything the panel composed sits between the figures and the
            reports: «كيف نقيس» belongs immediately after the numbers it
            defines, and the Vision 2030 band immediately before the shelf of
            documents a public body will file it against.
        -->
        <SectionRenderer :sections="sections" :skip="['stats', 'cta_band']" />

        <StoryCarousel
            :heading="storiesCopy.heading"
            :items="stories"
            :stories-total="storiesTotal"
            :all-url="storiesUrl"
        />

        <ReportsList :heading="reportsCopy.heading" :items="reports" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
