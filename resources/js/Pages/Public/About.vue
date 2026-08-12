<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

/**
 * /about — the last stop before the decision (§5).
 *
 * A visitor arrives here having already been convinced by a solutions page.
 * They are not asking what we sell; they are asking whether this is a serious
 * organisation to put their own name beside. So the page is narrative and
 * evidence, and it deliberately repeats none of the segment pages: the "how
 * we work" process block that used to sit here is on all four of those, and
 * `AboutPageTellsItsOwnStoryTest` fails the build if it comes back.
 *
 * The arc — story, model, milestones, figures, accreditations, Vision 2030,
 * team, showroom — lives in the section rows, not in this file. Everything
 * including the figures and the logo strip renders through SectionRenderer,
 * so the client can reorder the whole page from the panel. The datasets it
 * cannot type into a settings field are handed down through `data`, which is
 * how the figures here stay the same records the home page and /impact read.
 */
const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    impact: { type: Array, default: () => [] },
    /** Derived from when the figures were last edited — never a typed field. */
    measuredAt: { type: String, default: null },
    partners: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

/**
 * The hero's action. On this page it is «زوروا معرضنا» rather than the
 * site-wide "let's begin": a visitor reading the company's own story is
 * closest to being persuaded by the room itself, and the showroom is the one
 * piece of evidence no page can reproduce. The form is still at the bottom.
 */
const heroCopy = computed(() => section('hero'));
const ctaCopy = computed(() => section('cta_band'));

const datasets = computed(() => ({
    stats: { items: props.impact, measuredAt: props.measuredAt },
    logos: { items: props.partners },
}));

const RENDERED_HERE = ['hero', 'cta_band'];
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero
            :title="page.title"
            :subtitle="page.subtitle"
            :cta-label="heroCopy.ctaLabel"
            :cta-url="heroCopy.ctaUrl"
        />

        <SaduDivider />

        <SectionRenderer :sections="sections" :skip="RENDERED_HERE" :data="datasets" />

        <CtaBand
            v-if="ctaCopy.heading"
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
