<script setup>
import Container from '@/Components/ui/Container.vue';
import LeadField from '@/Components/forms/LeadField.vue';

/**
 * sections/CtaBand (§11.1) — navy ground with the lead field embedded.
 *
 * This is where the whole page has been heading. Per §5's governing rule,
 * every main section ends in a call to action leading here, and there is
 * exactly one destination: the single contact field.
 */
defineProps({
    heading: { type: String, default: null },
    reassurance: { type: String, default: null },
    submitLabel: { type: String, default: null },
    sectorHint: { type: String, default: null },
    campaign: { type: String, default: null },
});
</script>

<template>
    <section class="section band on-dark">
        <Container>
            <div class="band__inner">
                <LeadField
                    layout="inline"
                    :heading="heading"
                    :reassurance="reassurance"
                    :submit-label="submitLabel"
                    :sector-hint="sectorHint"
                    :campaign="campaign"
                />
            </div>
        </Container>
    </section>
</template>

<style scoped>
.band__inner {
    max-inline-size: 720px;
}

.band :deep(.lead__heading) {
    font-size: var(--fs-h2);
}
</style>
