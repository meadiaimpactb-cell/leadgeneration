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
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    solutions: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const gridCopy = computed(() => section('solutions_grid'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero :title="page?.title" :subtitle="page?.subtitle" />

        <SaduDivider />

        <SolutionsGrid :heading="gridCopy.heading" :items="solutions" />

        <SectionRenderer :sections="sections" :skip="['cta_band']" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
