<script setup>
import { computed } from 'vue';
import CampaignLayout from '@/Layouts/CampaignLayout.vue';
import Container from '@/Components/ui/Container.vue';
import CardsGrid from '@/Components/sections/CardsGrid.vue';
import PartnersLogos from '@/Components/sections/PartnersLogos.vue';
import LeadField from '@/Components/forms/LeadField.vue';
import SaduDivider from '@/Components/ui/SaduDivider.vue';

/**
 * A campaign landing page (§11.3).
 *
 * Hero › three value points › trust proof › one large lead field › minimal
 * footer. One goal, and the lead carries the campaign slug so every
 * submission is attributable to the campaign that produced it (§14.2).
 */
const props = defineProps({
    campaign: { type: Object, required: true },
    sections: { type: Array, default: () => [] },
    previewing: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
});

function section(type) {
    return props.sections.find((s) => s.type === type) ?? {};
}

const hero = computed(() => section('hero'));
const points = computed(() => section('cards'));
const proof = computed(() => section('logos'));
const form = computed(() => section('contact_block'));
</script>

<template>
    <CampaignLayout :seo="seo" :previewing="previewing" :campaign="campaign.slug">
        <section class="chero on-dark">
            <Container>
                <h1 v-if="hero.heading ?? campaign.title" class="display">
                    {{ hero.heading ?? campaign.title }}
                </h1>
                <p v-if="hero.subheading" class="chero__sub">{{ hero.subheading }}</p>
            </Container>
        </section>

        <SaduDivider />

        <CardsGrid :heading="points.heading" :items="points.settings?.items ?? []" />

        <PartnersLogos :heading="proof.heading" :items="proof.settings?.logos ?? []" />

        <section class="section cform">
            <Container>
                <div class="cform__inner">
                    <LeadField
                        layout="stacked"
                        :heading="form.heading"
                        :reassurance="form.subheading"
                        :submit-label="form.ctaLabel"
                        :campaign="campaign.slug"
                    />
                </div>
            </Container>
        </section>
    </CampaignLayout>
</template>

<style scoped>
.chero {
    padding-block: var(--s-10) var(--s-11);
}

.chero__sub {
    margin-block-start: var(--s-5);
    font-size: var(--fs-body-lg);
    color: rgba(255, 255, 255, 0.82);
    max-inline-size: 56ch;
}

.cform {
    background: var(--paper-alt);
}

.cform__inner {
    max-inline-size: 640px;
    margin-inline: auto;
}
</style>
