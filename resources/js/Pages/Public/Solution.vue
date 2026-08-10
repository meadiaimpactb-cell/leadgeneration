<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import SolutionsGrid from '@/Components/sections/SolutionsGrid.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

const props = defineProps({
    solution: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    related: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const relatedCopy = computed(() => section('solutions_grid'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <PublicLayout :seo="seo">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero
            :title="solution.name"
            :subtitle="solution.summary"
            :image="solution.image"
        />

        <SaduDivider />

        <SectionRenderer :sections="sections" :skip="['cta_band']" />

        <SolutionsGrid :heading="relatedCopy.heading" :items="related" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
