<script setup>
import Container from '@/Components/ui/Container.vue';
import LeadField from '@/Components/forms/LeadField.vue';

/**
 * sections/CtaBand (§11.1) — navy card with the lead form beside the pitch.
 *
 * This is where the whole page has been heading. Per §5's governing rule,
 * every main section ends in a call to action leading here, and there is
 * exactly one destination: the one form (§6.1).
 *
 * Split into two columns rather than stacked: the argument for contacting
 * stays visible while the form is filled, which is the difference between a
 * form a buyer completes and one they abandon halfway.
 */
defineProps({
    heading: { type: String, default: null },
    reassurance: { type: String, default: null },
    submitLabel: { type: String, default: null },
    sectorHint: { type: String, default: null },
    campaign: { type: String, default: null },
    eyebrow: { type: String, default: null },
    /** The small line under the submit button — response time, no spam. */
    note: { type: String, default: null },
    /** Page-specific label for the form's first field. The label only. */
    firstFieldLabel: { type: String, default: null },
    /** Which of a page's audiences pressed the button that led here. */
    interest: { type: String, default: null },
    /** Page-specific example for the optional message. The placeholder only. */
    messagePlaceholder: { type: String, default: null },
});
</script>

<template>
    <!-- Named so a hero action can point straight at the form. -->
    <section id="lead" class="band-outer">
        <Container>
            <div class="band on-dark">
                <!-- Sadu use 5 of 7: the thread on the card's reading edge. -->
                <span class="sadu-edge sadu-weave band__edge" aria-hidden="true" />

                <div class="band__grid">
                    <div class="band__pitch">
                        <p v-if="eyebrow" class="mono-label mono-label--gold">{{ eyebrow }}</p>
                        <h2 v-if="heading" class="band__title">{{ heading }}</h2>
                        <p v-if="reassurance" class="band__note">{{ reassurance }}</p>
                    </div>

                    <div class="band__form">
                        <!--
                            Heading and reassurance are rendered in the pitch
                            column above, so they are not passed again here —
                            LeadField would print a second copy of both.
                        -->
                        <LeadField
                            layout="inline"
                            :first-field-label="firstFieldLabel"
                            :message-placeholder="messagePlaceholder"
                            :submit-label="submitLabel"
                            :sector-hint="sectorHint"
                            :campaign="campaign"
                            :interest="interest"
                        />

                        <p v-if="note" class="band__submit-note">{{ note }}</p>
                    </div>
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.band-outer {
    padding-block: 0 var(--section-y);
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
    --sadu-colour: rgba(220, 173, 117, 0.55);
}

.band__grid {
    position: relative;
    display: grid;
    /*
     * `minmax(0, …)` on both tracks, not bare fr units.
     *
     * A bare `1.05fr 1fr` sizes each track to its content's minimum before
     * distributing space, and an input's intrinsic minimum is wide. The form
     * column wins, the pitch column collapses, and the Arabic heading breaks
     * to one word per line. This is the fix, not a refinement.
     */
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-7);
}

.band__pitch {
    min-inline-size: 0;
}

.band__title {
    margin-block-start: var(--s-4);
    font-family: var(--font-display);
    font-size: clamp(1.75rem, 3.2vw, 2.875rem);
    font-weight: 700;
    line-height: var(--lh-heading);
    letter-spacing: -0.01em;
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

.band__form {
    min-inline-size: 0;
}

.band__submit-note {
    margin-block-start: var(--s-4);
    font-size: var(--fs-sm);
    color: rgba(255, 255, 255, 0.55);
}

/*
 * Fields on this ground get a solid navy fill, never a translucent one: the
 * Sadu strip and the card edge would otherwise show through behind the text
 * the visitor is typing.
 */
.band :deep(.lead-input) {
    background: var(--navy-800);
    border: 1px solid rgba(220, 173, 117, 0.42);
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
 * The mono voice, at a size iOS will not fight.
 *
 * This was 0.90625rem. Safari zooms the whole page in when a field it is
 * focusing sets type below 16px, and it never zooms back out — so tapping
 * the email or phone box left the visitor stranded inside a magnified form,
 * on the one control the site exists for. `.lead-input` sets 18px in
 * components.css with a comment saying exactly that; this rule was undoing
 * it on every dark band, which is every page.
 *
 * 1rem is the floor, not a preference: the mono face still reads as the
 * technical voice beside the other fields, it is simply no longer small
 * enough to trigger the zoom.
 */
.band :deep(.lead-input--mono) {
    font-family: var(--font-mono);
    font-size: var(--fs-body);
    letter-spacing: 0.02em;
    text-align: start;
}

/* Two extra fields sit side by side; the contact field spans underneath. */
.band :deep(.lead__extras) {
    gap: var(--s-4);
}

/*
 * The submit button drops below the field rather than sitting beside it.
 * Inline, the three labelled fields above it end at the container edge and a
 * fourth control on the same line as the last one breaks that column.
 */
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
