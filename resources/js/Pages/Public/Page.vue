<script setup>
import { computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

/**
 * A page that is a pure section composition: About, and the legal pages.
 *
 * This is the template a client-created page uses, which is what makes
 * "add a page with no technical help" real (§9.1).
 */
const props = defineProps({
    page: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

const ctaCopy = computed(() => props.sections.find((s) => s.type === 'cta_band') ?? {});

// Legal pages read as documents, not as marketing surfaces.
const narrow = computed(() => props.page.narrow === true);
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero v-if="!narrow" :title="page.title" :subtitle="page.subtitle" />

        <header v-else class="legal-head">
            <div class="container">
                <h1>{{ page.title }}</h1>
                <p v-if="page.subtitle" class="legal-head__sub">{{ page.subtitle }}</p>
            </div>
        </header>

        <SaduDivider v-if="!narrow" />

        <SectionRenderer :sections="sections" :skip="['cta_band']" />

        <CtaBand
            v-if="!narrow && ctaCopy.heading"
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>

<style scoped>
.legal-head {
    padding-block: var(--s-9) var(--s-5);
}

.legal-head__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
}
</style>
