<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import LeadField from '@/Components/forms/LeadField.vue';
import { useSettingText } from '@/Composables/useSettingText';
import { useLeadSource } from '@/Composables/useLeadSource';

/**
 * sections/LandingContact — the one form on the site.
 *
 * Management's decision of 7 September 2026 is that the site carries exactly
 * one contact form, here, at the foot of the page, and that no section may
 * hold a second one. Every call to action above scrolls here.
 *
 * THE COMPOSITION IS `CtaBand`'S, deliberately. That band — a navy card with
 * the Sadu thread on its reading edge, the pitch on one side and the fields
 * on the other — is the approved design and was on every page of the site
 * this one replaced. This block first shipped as a centred stack on sand,
 * which was a different thing altogether at the exact moment the page is
 * asking for something.
 *
 * It is not `CtaBand` itself: that component takes its heading and its
 * reassurance and hands them to `LeadField` to print, and it is registered as
 * the `cta_band` section type — which the same decision forbids on this page,
 * because it can be added again from the panel and would be a second form.
 * The design is shared; the guarantee of one form is not negotiable.
 *
 * What the visitor sees stays at two controls. The segment they arrived from
 * is not a third field: it rides along in `sector_hint`, a column that already
 * exists, and it changes only the one line of context above the form.
 */
const props = defineProps({
    heading: { type: String, default: null },
    /** The reassurance line, HTML authored in the panel. */
    body: { type: String, default: null },
    /** `{ submitLabel, contexts }` from the section's settings. */
    settings: { type: Object, default: () => ({}) },
});

const { text } = useSettingText();
const { source } = useLeadSource();

const submitLabel = computed(() => text(props.settings, 'submitLabel'));

/**
 * The line that reflects which button brought the visitor here.
 *
 * Absent until they press one, and absent entirely if the client has not
 * written one for that segment — an unwritten line is no line, never a key
 * printed at a visitor (§22.1).
 */
const context = computed(() => {
    if (!source.value) {
        return null;
    }

    return text(props.settings.contexts ?? {}, source.value);
});
</script>

<template>
    <div class="lcontact-outer">
        <Container>
            <div class="band on-dark">
                <!-- The Sadu thread on the card's reading edge — the same use
                     the approved band has always made of it. -->
                <span class="sadu-edge sadu-weave band__edge" aria-hidden="true" />

                <div class="band__grid">
                    <div class="band__pitch">
                        <h2 v-if="heading" class="band__title">{{ heading }}</h2>

                        <div v-if="body" class="band__note" v-html="body" />

                        <!--
                            aria-live so a screen reader hears the context
                            change when a button above sets it — the sighted
                            visitor sees the line appear, and this is the same
                            event announced.
                        -->
                        <p v-if="context" class="band__context" aria-live="polite">
                            {{ context }}
                        </p>
                    </div>

                    <div class="band__form">
                        <!-- Heading and reassurance are drawn in the pitch
                             column, so they are not passed again — LeadField
                             would print a second copy of both. -->
                        <LeadField
                            layout="inline"
                            :submit-label="submitLabel"
                            :sector-hint="source"
                        />
                    </div>
                </div>
            </div>
        </Container>
    </div>
</template>

<style scoped>
.lcontact-outer {
    padding-block: var(--section-y);
}

.band {
    position: relative;
    background: var(--navy-900);
    padding: var(--s-8) var(--s-6);
    overflow: hidden;
}

.band__edge {
    --sadu-tile: 20px;
    --sadu-edge-w: 20px;
    --sadu-colour: rgb(var(--gold-rgb) / 0.55);
}

.band__grid {
    position: relative;
    display: grid;
    /*
     * `minmax(0, …)` on both tracks, not bare fr units — the reason is
     * CtaBand's and it holds here: a bare `1fr` sizes each track to its
     * content's minimum first, an input's intrinsic minimum is wide, and the
     * pitch column collapses until the Arabic heading breaks one word to a
     * line.
     */
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-7);
}

.band__pitch {
    min-inline-size: 0;
}

.band__title {
    font-family: var(--font-display);
    font-size: clamp(1.75rem, 3.2vw, 2.875rem);
    font-weight: 700;
    line-height: var(--lh-heading);
    letter-spacing: var(--tracking-heading);
    color: #fff;
    max-inline-size: 20ch;
}

.band__note {
    margin-block-start: var(--s-4);
    font-size: 1.0625rem;
    line-height: var(--lh-body);
    color: rgba(255, 255, 255, 0.74);
    max-inline-size: 44ch;
}

.band__note :deep(p) {
    margin: 0;
}

/* The segment line: gold, on its own rule, so it reads as an answer to the
   button just pressed rather than as more of the paragraph above. */
.band__context {
    margin-block-start: var(--s-5);
    padding-inline-start: var(--s-3);
    border-inline-start: 2px solid var(--gold-400);
    color: var(--gold-400);
    font-size: var(--fs-body);
}

.band__form {
    min-inline-size: 0;
}

/*
 * Fields on this ground get a solid navy fill, never a translucent one: the
 * Sadu strip and the card edge would otherwise show through behind the text
 * the visitor is typing.
 */
.band :deep(.lead-input) {
    background: var(--navy-800);
    border: 1px solid rgb(var(--gold-rgb) / 0.42);
    color: #fff;
    min-block-size: 54px;
}

.band :deep(.lead-input::placeholder) {
    color: rgba(255, 255, 255, 0.45);
}

.band :deep(.lead__label) {
    color: rgba(255, 255, 255, 0.82);
}

/*
 * 1rem is the floor, not a preference. Safari zooms the page in when it
 * focuses a field set below 16px and never zooms back out, which strands the
 * visitor inside a magnified form on the one control the site exists for.
 */
.band :deep(.lead-input--mono) {
    font-family: var(--font-mono);
    font-size: var(--fs-body);
    letter-spacing: 0.02em;
    text-align: start;
}

.band :deep(.lead__extras) {
    gap: var(--s-4);
}

/* The submit drops below the fields rather than sitting beside the last one. */
.band :deep(.lead__row) {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-4);
    margin-block-start: var(--s-4);
}

.band :deep(.lead__submit) {
    justify-self: start;
    white-space: nowrap;
    min-block-size: 56px;
}

@media (min-width: 900px) {
    .band {
        padding: var(--s-9) var(--s-8);
    }

    .band__grid {
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
        gap: var(--s-8);
        align-items: center;
    }
}
</style>
