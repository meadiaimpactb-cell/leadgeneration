<script setup>
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';

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
    /**
     * The line under the heading, and the closing note under the steps.
     *
     * Both columns already existed on `section_translations` and neither was
     * drawn here, so an editor could type them, see them saved, and never
     * find them on the page. The landing page's «كيف نعمل؟» block carries
     * both — a subtitle and the «النتيجة؟» line that closes the argument.
     */
    subheading: { type: String, default: null },
    /** Closing prose under the steps. HTML, authored in the panel. */
    body: { type: String, default: null },
    /** `[{ title, body }]` from the section's settings. */
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();

/**
 * A step's text in the current language. The rule — both languages on each
 * item, no fallback between them — is `useSettingText`, shared with every
 * other section whose repeatable content lives in `settings`.
 */
const { text } = useSettingText();
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

            <p v-if="subheading" class="process__sub reveal">{{ subheading }}</p>

            <!-- An ordered list, because the order is the content. -->
            <!-- Capped at five: six stages across a 768px row give each one
                 about 120px, which is narrower than the shortest Arabic step
                 title. Below that the grid is a single column anyway. -->
            <ol class="steps" :style="{ '--steps-count': Math.min(items.length, 5) }">
                <li v-for="(step, i) in items" :key="i" class="step reveal">
                    <h3 v-if="text(step, 'title')" class="step__title">
                        {{ text(step, 'title') }}
                    </h3>
                    <p v-if="text(step, 'body')" class="step__body">
                        {{ text(step, 'body') }}
                    </p>
                </li>
            </ol>

            <div v-if="body" class="prose__body process__note reveal" v-html="body" />
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

.process__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    max-inline-size: 60ch;
}

.process__note {
    margin-block-start: var(--s-6);
    max-inline-size: 70ch;
}
</style>
