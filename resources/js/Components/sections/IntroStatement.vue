<script setup>
import Container from '@/Components/ui/Container.vue';
import SectionIndex from '@/Components/ui/SectionIndex.vue';
import { useReveal } from '@/Composables/useReveal';

/**
 * sections/IntroStatement (§11.1) — the manifesto.
 *
 * A narrow numbered margin beside the copy, as the approved design has it.
 * The number and the caption take a line each: on one line in a 190px column
 * the string wraps mid-run, and a wrapped bidi fragment reorders — which is
 * how "01 / 05" once rendered as "05 / 01".
 *
 * The body's first paragraph carries the statement, and anything after a blank
 * line is the supporting note beneath it — so the client controls both from
 * the one field they already have.
 */
const props = defineProps({
    body: { type: String, default: null },
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    slug: { type: String, default: null },
});

const { root } = useReveal();

const parts = () =>
    (props.body ?? '')
        .split(/\r?\n\s*\r?\n/)
        .map((p) => p.trim())
        .filter(Boolean);

const statement = () => parts()[0] ?? null;
const note = () => parts().slice(1).join('\n\n') || null;
</script>

<template>
    <section v-if="body" ref="root" class="section intro">
        <Container>
            <div class="intro__grid">
                <div class="intro__margin reveal">
                    <SectionIndex
                        layout="stacked"
                        :index="index"
                        :total="total"
                        :slug="slug"
                    />
                    <span class="intro__rule" aria-hidden="true" />
                </div>

                <div class="intro__body">
                    <p class="intro__statement reveal">{{ statement() }}</p>
                    <p v-if="note()" class="intro__note reveal">{{ note() }}</p>
                </div>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.intro__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: var(--s-6);
    align-items: start;
}

.intro__margin {
    padding-block-start: var(--s-3);
}

.intro__rule {
    display: block;
    margin-block-start: var(--s-5);
    block-size: 1px;
    background: rgba(0, 37, 70, 0.22);
}

.intro__statement {
    /*
     * The one paragraph on the page set in the brand face at weight 500 —
     * lighter than a heading, heavier than body. It is the site's statement of
     * what it is, and the only block with no image and no link, so it carries
     * the identity type and nothing else has to.
     *
     * 52ch, not 34: at 34 the line broke every four or five words and the
     * paragraph read as a stack rather than as prose.
     */
    margin: 0;
    font-family: var(--font-display);
    font-size: clamp(1.5rem, 2.5vw, 2.25rem);
    font-weight: 500;
    line-height: 1.62;
    letter-spacing: var(--tracking-heading);
    color: var(--navy-900);
    max-inline-size: 52ch;
    text-wrap: pretty;
    white-space: pre-line;
}

.intro__note {
    margin-block-start: var(--s-6);
    font-size: 1.09375rem;
    line-height: var(--lh-body);
    color: var(--text-muted);
    max-inline-size: 76ch;
    white-space: pre-line;
}

@media (min-width: 900px) {
    .intro__grid {
        grid-template-columns: 190px minmax(0, 1fr);
        gap: var(--s-8);
    }
}
</style>
