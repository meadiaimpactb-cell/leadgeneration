<script setup>
import { usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/ProcessSteps — "how we work with you", as a procedure.
 *
 * Replaces a paragraph describing the process with the process itself. A
 * government buyer is purchasing the clarity of the procedure as much as the
 * object: a paragraph cannot be checked against a procurement policy, four
 * numbered steps can.
 *
 * The steps are the section's `items` setting, so the client writes and
 * reorders them in the panel. Nothing renders until they do (§22.1).
 */
defineProps({
    heading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    /** `[{ title, body }]` from the section's settings. */
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
const page = usePage();

/**
 * A step's text in the current language.
 *
 * `settings` is a single JSON column on the section — it is NOT per-locale
 * like the translated fields are — so a repeatable list inside it carries
 * both languages on each item, `title` and `title_en`. Falling back to the
 * Arabic key would serve Arabic to an English reader, which §12 forbids, so
 * a missing English string yields nothing and the line simply does not
 * render.
 *
 * The media library will make this unnecessary for images; for repeatable
 * text it is the shape the section builder already stores.
 */
function text(step, field) {
    return page.props.locale === 'en' ? (step[`${field}_en`] ?? null) : (step[field] ?? null);
}
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="section process"
        :aria-labelledby="heading ? 'process-heading' : undefined"
    >
        <Container>
            <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
            <h2 v-if="heading" id="process-heading" class="h2 process__title reveal">
                {{ heading }}
            </h2>

            <!-- An ordered list, because the order is the content. -->
            <ol class="steps">
                <li v-for="(step, i) in items" :key="i" class="step reveal">
                    <h3 v-if="text(step, 'title')" class="step__title">{{ text(step, 'title') }}</h3>
                    <p v-if="text(step, 'body')" class="step__body">{{ text(step, 'body') }}</p>
                </li>
            </ol>
        </Container>
    </section>
</template>

<style scoped>
.process__title {
    margin-block-start: var(--s-4);
    margin-block-end: var(--s-8);
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

/*
 * The last step's connector stops at its own node rather than running off
 * the end of the row — a dotted rule leaving the page implies a fifth step.
 */
.steps > :last-child::before {
    inset-inline-end: auto;
    inline-size: 12px;
}
</style>
