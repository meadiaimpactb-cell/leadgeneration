<script setup>
import { computed, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import PageHero from '@/Components/sections/PageHero.vue';
import SectionRenderer from '@/Components/sections/SectionRenderer.vue';
import TrainingTracks from '@/Components/sections/TrainingTracks.vue';
import ProcessSteps from '@/Components/sections/ProcessSteps.vue';
import AudienceSplit from '@/Components/sections/AudienceSplit.vue';
import ImpactStats from '@/Components/sections/ImpactStats.vue';
import StoryCarousel from '@/Components/sections/StoryCarousel.vue';
import CtaBand from '@/Components/sections/CtaBand.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';
import { useSettingText } from '@/Composables/useSettingText';

/**
 * Training and empowerment (§5) — the page with two readers.
 *
 * Everywhere else on this site addresses one buyer. Here an artisan deciding
 * whether to join a track and an institution deciding whether to fund one read
 * the same page, and the arc is built so neither of them arrives at the bottom
 * to find a form written for the other: the tracks and how they run are for
 * both, the split section gives each their own way in, and the form takes the
 * shape of whichever button was pressed.
 *
 * What never changes is the form itself — the same three fields, the same
 * endpoint, the same requirement (§6.1). The heading, the message example and
 * a stored interest tag are all that differ, so the sales team can tell the two
 * apart without the visitor having been asked to classify themselves.
 */
const props = defineProps({
    page: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    programs: { type: Array, default: () => [] },
    /** The training figures, read from the one impact register. */
    impact: { type: Array, default: () => [] },
    measuredAt: { type: String, default: null },
    /** Stories tagged as graduates of these tracks. Empty until there are any. */
    graduates: { type: Array, default: () => [] },
    breadcrumbs: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
    previewing: { type: Boolean, default: false },
});

const { text } = useSettingText();

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const heroCopy = computed(() => section('hero'));
const tracksCopy = computed(() => section('training_tracks'));
const processCopy = computed(() => section('process_steps'));
const splitCopy = computed(() => section('audience_split'));
const statsCopy = computed(() => section('stats'));
const graduatesCopy = computed(() => section('story_carousel'));
const ctaCopy = computed(() => section('cta_band'));

const secondaryLabel = computed(() => text(heroCopy.value.settings, 'secondaryLabel'));

/**
 * Which audience is being served right now — null until somebody says.
 *
 * Not defaulted to either one. A visitor who simply scrolls to the form has
 * told us nothing, and guessing would put a tag on the lead that the sales
 * team would then have to distrust.
 */
const interest = ref(null);

/**
 * The form's two faces, both written in the panel.
 *
 * `variants` is keyed by the same tag the lead is stored with, so the copy for
 * an audience and the label it is filed under cannot drift apart. Anything a
 * variant does not override falls back to the section's own heading, which is
 * what the page shows before a button is pressed.
 */
const variant = computed(() => ctaCopy.value.settings?.variants?.[interest.value] ?? null);

const ctaHeading = computed(() => text(variant.value, 'heading') ?? ctaCopy.value.heading);
const ctaReassurance = computed(
    () => text(variant.value, 'subheading') ?? ctaCopy.value.subheading,
);
const ctaMessagePlaceholder = computed(() => text(variant.value, 'messagePlaceholder'));

/**
 * Take the visitor to the form as the audience they said they were.
 *
 * The tag comes from the button's own setting rather than from a literal here:
 * the vocabulary is content, and the panel owns it.
 */
function choose(tag) {
    interest.value = tag ?? null;

    if (typeof document === 'undefined') return;

    const form = document.getElementById('lead');

    if (form === null) return;

    const still = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    form.scrollIntoView({
        behavior: still ? 'auto' : 'smooth',
        block: 'center',
    });

    // After the scroll settles, not before — focusing first makes the browser
    // jump to the field and cancels the smooth scroll.
    window.setTimeout(
        () => form.querySelector('input[name="contact"]')?.focus({ preventScroll: true }),
        still ? 0 : 600,
    );
}

/** The hero's second action is the sponsor's door; the first is a plain link. */
function heroAction(which) {
    if (which === 'secondary') choose(heroCopy.value.settings?.secondaryInterest);
}

/** Rendered explicitly below, so the generic renderer must not repeat them. */
const RENDERED_HERE = [
    'hero',
    'training_tracks',
    'process_steps',
    'audience_split',
    'stats',
    'story_carousel',
    'cta_band',
];
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
            @action="heroAction"
        />

        <SaduDivider />

        <TrainingTracks :heading="tracksCopy.heading" :items="programs" />

        <!-- The same procedure component the segment pages use. Its last step
             is what separates this from a training institute: the track ends
             at Amad Craft's own orders, not at a certificate. -->
        <ProcessSteps
            :heading="processCopy.heading"
            :eyebrow="processCopy.settings?.eyebrow"
            :items="processCopy.settings?.items ?? []"
        />

        <AudienceSplit
            :heading="splitCopy.heading"
            :subheading="splitCopy.subheading"
            :items="splitCopy.settings?.items ?? []"
            @choose="choose"
        />

        <ImpactStats
            variant="band"
            :heading="statsCopy.heading"
            :eyebrow="statsCopy.settings?.eyebrow"
            :items="impact"
            :measured-at="measuredAt"
        />

        <!-- Graduates of these tracks, from the same records as the stories on
             /impact — one story, read twice. Silent until a story is tagged. -->
        <StoryCarousel :heading="graduatesCopy.heading" :items="graduates" />

        <!-- Whatever else the panel composed. The FAQ arrives here. -->
        <SectionRenderer :sections="sections" :skip="RENDERED_HERE" />

        <CtaBand
            :heading="ctaHeading"
            :reassurance="ctaReassurance"
            :note="ctaCopy.body"
            :submit-label="ctaCopy.ctaLabel"
            :message-placeholder="ctaMessagePlaceholder"
            :interest="interest"
        />
    </PublicLayout>
</template>
