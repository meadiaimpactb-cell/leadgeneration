<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import ProductShowcase from '@/Components/sections/ProductShowcase.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

/**
 * The product showcase page (§5) — informational only, no purchase path.
 */
const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    // Filtering and paging happen on the server (§13) — see ProductShowcase.
    activeCategory: { type: String, default: null },
    allUrl: { type: String, default: null },
    totalCount: { type: Number, default: 0 },
    pagination: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

const inertia = usePage();

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const showcaseCopy = computed(() => section('product_showcase'));
const ctaCopy = computed(() => section('cta_band'));

// The outbound store link's wording is a client-managed setting (§14.1).
const storeLabel = computed(
    () => inertia.props.settings?.[`store.label.${inertia.props.locale}`] ?? null
);
</script>

<template>
    <PublicLayout :seo="seo" :previewing="previewing">
        <Breadcrumb :items="breadcrumbs" />

        <PageHero :title="page?.title" :subtitle="page?.subtitle" />

        <SaduDivider />

        <ProductShowcase
            :heading="showcaseCopy.heading"
            :subheading="showcaseCopy.subheading"
            :items="products"
            :categories="categories"
            :active-category="activeCategory"
            :all-url="allUrl"
            :total-count="totalCount"
            :pagination="pagination"
            :store-label="storeLabel"
        />

        <SectionRenderer :sections="sections" :skip="['cta_band']" />

        <CtaBand
            :heading="ctaCopy.heading"
            :reassurance="ctaCopy.subheading"
            :submit-label="ctaCopy.ctaLabel"
        />
    </PublicLayout>
</template>
