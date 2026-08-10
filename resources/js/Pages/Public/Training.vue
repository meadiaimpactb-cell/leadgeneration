<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import TrainingTracks from '@/Components/sections/TrainingTracks.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    programs: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const tracksCopy = computed(() => section('training_tracks'));
const ctaCopy = computed(() => section('cta_band'));
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero :title="page?.title" :subtitle="page?.subtitle" />

        <SaduDivider />

        <TrainingTracks :heading="tracksCopy.heading" :items="programs" />

        <SectionRenderer :sections="sections" :skip="['cta_band']" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
