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
/*
 * `label` arrives twice: once inside `settings`, where this reads it, and once
 * as a spread key the renderer hands every component. The second copy has no
 * matching prop, so Vue would print it on the <section> as an attribute — the
 * approved Arabic wording, in the markup of the English page.
 */
defineOptions({ inheritAttrs: false });

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
    <section v-if="label" ref="root" class="segment-cta">
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
/*
 * No band behind the button.
 *
 * This block used to paint a full-bleed navy field and stand one orange
 * button in the middle of it, which spent a whole screen-width of the
 * page's boldest colour on a single control — and put a hard navy edge
 * between two halves of the same argument. The button carries itself:
 * `--cta-lg` is the 18px/700 weight the identity sizes `#D7653B` for
 * precisely so it can stand on the page's own light ground and still
 * clear AA.
 *
 * The section keeps its own vertical room so the button is not read as
 * belonging to the paragraph above it.
 */
.segment-cta {
    padding-block: var(--s-7);
}

.segment-cta__inner {
    display: flex;
    justify-content: center;
}
</style>
