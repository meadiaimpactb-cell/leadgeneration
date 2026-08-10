<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    partners: { type: Array, default: () => [] },
    accreditations: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

/**
 * Three logo blocks, each headed by copy the client wrote. The section rows
 * are matched by `settings.group` so the admin panel can label and reorder
 * them without this file changing.
 */
function groupCopy(group) {
    return props.sections.find((s) => s.type === 'logos' && s.settings?.group === group) ?? {};
}

const ctaCopy = computed(() => props.sections.find((s) => s.type === 'cta_band') ?? {});
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero :title="page?.title" :subtitle="page?.subtitle" />

        <SaduDivider />

        <PartnersLogos :heading="groupCopy('partner').heading" :items="partners" />
        <PartnersLogos :heading="groupCopy('accreditation').heading" :items="accreditations" />
        <PartnersLogos :heading="groupCopy('client').heading" :items="clients" />

        <SectionRenderer :sections="sections" :skip="['cta_band', 'logos']" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
