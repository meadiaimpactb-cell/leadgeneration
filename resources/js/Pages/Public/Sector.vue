<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Container from '@/Components/ui/Container.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import SegmentHero from '@/Components/sections/SegmentHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import ProcessSteps from '@/Components/sections/ProcessSteps.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * An audience-segment page (§11.2) — the template all four share.
 *
 * The arc is fixed because it answers a buyer's questions in the order they
 * ask them: who is this for → what do you offer me → how does it actually
 * work → what have you done → what do others ask → where else should I look
 * → talk to us → come and see it.
 *
 * The lead field at the bottom carries `sectorHint`, so the sales team can
 * see which segment a prospect came from without ever having asked (§6.1).
 */
const props = defineProps({
    sector: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    solutions: { type: Array, default: () => [] },
    impact: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    /** The other three segments, so the visitor stays on the site. */
    siblings: { type: Array, default: () => [] },
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();
const page = usePage();

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const heroCopy = computed(() => section('hero'));

/**
 * The hero's second action.
 *
 * A section translation carries one `cta_label`/`cta_url` pair, so a second
 * button has to live in `settings` — which is a single JSON column, not a
 * per-locale one. It therefore carries both languages, `secondaryLabel` and
 * `secondaryLabel_en`, and this picks the right one.
 *
 * No fallback to Arabic: §12 is explicit that an English visitor is never
 * served Arabic. A missing English label means no second button, which is
 * correct — half a translated page is worse than a shorter one.
 */
const secondary = computed(() => {
    const s = heroCopy.value.settings ?? {};
    const en = page.props.locale === 'en';

    return {
        label: en ? (s.secondaryLabel_en ?? null) : (s.secondaryLabel ?? null),
        url: en ? (s.secondaryUrl_en ?? s.secondaryUrl ?? null) : (s.secondaryUrl ?? null),
    };
});
const processCopy = computed(() => section('process_steps'));
const impactCopy = computed(() => section('stats'));
const clientsCopy = computed(() => section('logos'));
const ctaCopy = computed(() => section('cta_band'));

/**
 * The composed sections, minus the ones this page renders itself. Without
 * the skip list they would appear twice — once here and once in their own
 * dedicated block below.
 */
const RENDERED_HERE = ['hero', 'process_steps', 'stats', 'logos', 'cta_band'];
</script>

<template>
    <PublicLayout :seo="seo">
        <Breadcrumb :items="breadcrumbs" />

        <SegmentHero
            :index="index"
            :total="total"
            :slug="sector.slug"
            :title="sector.name"
            :subtitle="sector.summary"
            :image="sector.image"
            :cta-label="heroCopy.ctaLabel"
            :cta-url="heroCopy.ctaUrl"
            :secondary-label="secondary.label"
            :secondary-url="secondary.url"
        />

        <!-- The trust strip sits directly under the hero: for an institutional
             buyer, who else has bought is the first question after what. -->
        <PartnersLogos :heading="clientsCopy.heading" :items="clients" />

        <!-- Whatever the client composed: cards, media split, FAQ, quote… -->
        <SectionRenderer :sections="sections" :skip="RENDERED_HERE" />

        <ProcessSteps
            :heading="processCopy.heading"
            :eyebrow="processCopy.settings?.eyebrow"
            :items="processCopy.settings?.items ?? []"
        />

        <ImpactStats
            variant="band"
            :heading="impactCopy.heading"
            :eyebrow="impactCopy.settings?.eyebrow"
            :items="impact"
        />

        <!-- The other three segments. Small, horizontal, and last before the
             form: a visitor who has read this far and is on the wrong page
             should find the right one without going back to the menu. -->
        <section v-if="siblings.length" class="section siblings">
            <Container>
                <h2 class="h2 siblings__title">{{ t('common.other_segments') }}</h2>

                <ul class="siblings__list">
                    <li v-for="item in siblings" :key="item.id">
                        <Link :href="item.url" class="sibling">
                            <span class="sibling__name">{{ item.name }}</span>
                            <span class="arrow" aria-hidden="true">&#8594;</span>
                        </Link>
                    </li>
                </ul>
            </Container>
        </section>

        <CtaBand
            :heading="ctaCopy.heading"
            :eyebrow="ctaCopy.settings?.eyebrow"
            :reassurance="ctaCopy.subheading"
            :note="ctaCopy.body"
            :submit-label="ctaCopy.ctaLabel"
            :sector-hint="sector.key"
        />
    </PublicLayout>
</template>

<style scoped>
.siblings__title {
    margin-block-end: var(--s-6);
    font-size: clamp(1.5rem, 2.4vw, 2.125rem);
}

.siblings__list {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
    list-style: none;
}

/*
 * Cut, gold-edged, and it lifts rather than grows on hover: a scale
 * transform on a chamfered box softens the cut for the duration of the
 * animation, which is the one thing this shape must not do.
 */
.sibling {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-4);
    padding: var(--s-5);
    min-block-size: 44px;
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    color: var(--navy-900);
    font-weight: 600;
    transition:
        transform var(--dur-micro) var(--ease),
        box-shadow var(--dur-micro) var(--ease);
}

.sibling:hover,
.sibling:focus-visible {
    transform: translateY(-3px);
    box-shadow:
        inset 0 0 0 1px var(--gold-400),
        0 10px 24px rgba(0, 37, 70, 0.1);
}

.sibling .arrow {
    color: var(--action-600);
}

.sibling:hover .arrow {
    transform: translateX(4px);
}

html[dir='rtl'] .sibling:hover .arrow {
    transform: scaleX(-1) translateX(4px);
}

@media (min-width: 768px) {
    .siblings__list {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
