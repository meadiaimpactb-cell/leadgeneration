<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';
import { useLeadSource } from '@/Composables/useLeadSource';

/**
 * sections/SegmentCta — the band that closes each audience section.
 *
 * Deliberately NOT `cta_band`. That component embeds a `LeadField`, and the
 * management decision of 7 September 2026 allows exactly one form on the
 * site, in `#contact`. This is the same band with a button in place of the
 * field: it carries the visitor, and their segment, to that one form.
 *
 * One button, one destination, no second field to fill in on the way.
 */
const props = defineProps({
    /** government | partner | artisan — written to the lead's `sector_hint`. */
    source: { type: String, default: null },
    /** `{ label, label_en }` from the section's settings. */
    settings: { type: Object, default: () => ({}) },
});

const { root } = useReveal();
const { text } = useSettingText();
const { goToForm } = useLeadSource();

const label = computed(() => text(props.settings, 'label'));
</script>

<template>
    <section v-if="label" ref="root" class="segment-cta on-dark">
        <Container>
            <div class="segment-cta__inner reveal">
                <Button variant="cta-lg" href="#contact" @click="goToForm($event, source)">
                    {{ label }}
                </Button>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.segment-cta {
    background: var(--navy-900);
    padding-block: var(--s-7);
}

.segment-cta__inner {
    display: flex;
    justify-content: center;
}
</style>
