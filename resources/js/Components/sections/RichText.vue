<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import { useReveal } from '@/Composables/useReveal';
import { useSettingText } from '@/Composables/useSettingText';
import SectionIndex from '@/Components/ui/SectionIndex.vue';

/**
 * sections/RichText (§10.5).
 *
 * `body` is HTML authored in the admin panel's editor. It is rendered with
 * v-html, which is safe here only because the editor is behind authentication
 * and its output is sanitised server-side before it is stored — never render
 * visitor-supplied HTML through this component.
 */
const props = defineProps({
    /**
     * The small label above the heading — the site's `.eyebrow`.
     *
     * Every other major section type takes one and this one did not, so a
     * long editorial block had no way to announce what part of the argument
     * it belonged to. The landing page uses it to carry the brief's own
     * «القسم الأول / الثاني / الثالث» labels, which is what restores the
     * running-number rhythm the home page had.
     */
    eyebrow: { type: String, default: null },
    /**
     * The section's raw settings, for the bilingual eyebrow.
     *
     * `settings` is one JSON column shared by both locales, so a translated
     * label has to carry `eyebrow` and `eyebrow_en` on the same row — the
     * rule `useSettingText` implements everywhere else. Without this an
     * English reader met an Arabic label above an English heading, which §12
     * rates as worse than no label at all.
     */
    settings: { type: Object, default: () => ({}) },
    /**
     * The chapter number in the margin — "03 / 06".
     *
     * The editorial spine the home page had and a single long landing page
     * needs more, not less: twenty-odd blocks in one scroll are a stack until
     * something tells the reader how far through the argument they are.
     *
     * Derived from the section's place, never written, so reordering in the
     * panel renumbers rather than lies. The counter only — no caption: the
     * captions on the old home page were Latin words, and `.mono-label`
     * carries letter-spacing, which §10.3 forbids on Arabic outright.
     */
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    heading: { type: String, default: null },
    /**
     * The line under the heading.
     *
     * `section_translations` has carried this column since the schema was
     * built and this component was the one that never drew it — so a
     * subheading typed into the panel for a rich_text block was saved,
     * shown in the editor, and silently absent from the page. The landing
     * page leans on it heavily; every other rich_text section that has one
     * gains it too, which is what the editor already expected.
     */
    subheading: { type: String, default: null },
    body: { type: String, default: null },
    /**
     * An optional photograph beside the prose, cut to the octagon.
     *
     * Added for /about, where the opening story is the page's whole argument
     * and a full-width column of text with nothing beside it reads as a
     * document rather than as an editorial spread. Every other rich_text
     * section on the site has no image and is untouched by this.
     */
    image: { type: Object, default: null },
});

const { root } = useReveal();
const { text } = useSettingText();

/** The written label, in the language being read. */
const label = computed(() => text(props.settings, 'eyebrow') ?? props.eyebrow);
</script>

<template>
    <section v-if="body || heading" ref="root" class="section">
        <Container>
            <div
                class="editorial"
                :class="{ 'editorial--illustrated': image, 'editorial--indexed': index }"
            >
                <div v-if="index" class="editorial__margin reveal" aria-hidden="true">
                    <SectionIndex layout="stacked" :index="index" :total="total" />
                    <span class="editorial__rule" />
                </div>

                <div class="prose reveal">
                    <span v-if="label" class="eyebrow">{{ label }}</span>
                    <h2 v-if="heading">{{ heading }}</h2>
                    <p v-if="subheading" class="prose__sub">{{ subheading }}</p>
                    <div v-if="body" class="prose__body" v-html="body" />
                </div>

                <figure v-if="image" class="editorial__figure reveal">
                    <div class="editorial__frame cut-framed">
                        <img
                            class="editorial__img cut"
                            :src="image.webp ?? image.url"
                            :alt="image.alt ?? ''"
                            :width="image.width ?? undefined"
                            :height="image.height ?? undefined"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>

                    <figcaption v-if="image.caption" class="editorial__caption">
                        {{ image.caption }}
                    </figcaption>
                </figure>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.editorial {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-8);
    align-items: center;
}

.editorial__figure {
    margin: 0;
}

.editorial__img {
    display: block;
    inline-size: 100%;
    block-size: 100%;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    background: var(--placeholder-warm);
}

.editorial__caption {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1024px) {
    /*
     * The text column keeps its comfortable measure and the photograph takes
     * the rest — the opposite of an even split, which would either stretch
     * the line length past readable or shrink the establishing shot to a
     * thumbnail.
     */
    .editorial--illustrated {
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
        gap: var(--s-10);
    }
}

.prose {
    max-inline-size: 68ch;
}

.prose__body {
    margin-block-start: var(--s-5);
    font-size: var(--fs-body-lg);
    color: var(--text);
}

.prose__body :deep(p) {
    margin-block-end: var(--s-4);
}

.prose__body :deep(h3) {
    margin-block: var(--s-7) var(--s-3);
}

.prose__body :deep(ul),
.prose__body :deep(ol) {
    margin-block-end: var(--s-4);
    padding-inline-start: var(--s-5);
    list-style: disc;
}

.prose__body :deep(li) {
    margin-block-end: var(--s-2);
}

.prose__body :deep(a) {
    color: var(--link);
    text-decoration: underline;
    text-underline-offset: 3px;
}

.prose__body :deep(strong) {
    font-weight: 600;
    color: var(--navy-900);
}

.prose__sub {
    margin-block-start: var(--s-3);
    color: var(--text-muted);
    font-size: var(--fs-body-lg);
    max-inline-size: 60ch;
}

/*
 * THE MARGIN COLUMN
 *
 * Lifted from IntroStatement, which is where this composition on this site
 * comes from — the number, a rule under it, and the prose set beside rather
 * than beneath. Single column until 900px, because a 190px margin on a phone
 * is not a margin, it is a wasted third of the screen.
 */
.editorial__margin {
    padding-block-start: var(--s-3);
}

.editorial__rule {
    display: block;
    margin-block-start: var(--s-5);
    block-size: 1px;
    background: rgba(0, 37, 70, 0.22);
}

@media (min-width: 900px) {
    .editorial--indexed {
        grid-template-columns: 190px minmax(0, 1fr);
        gap: var(--s-8);
        align-items: start;
    }
}

/*
 * THE OPENING PROPOSITION
 *
 * In an indexed block the first paragraph is not a paragraph — it is the
 * claim the rest of the section answers. IntroStatement set exactly that one
 * sentence in the brand face at weight 500, lighter than a heading and
 * heavier than body, and it is the thing that made the old home page read as
 * written rather than as filled in.
 *
 * Same treatment here, a size down: this opens a chapter rather than the
 * whole site, and matching the manifesto exactly would put five statements on
 * one page at the weight that only works when there is one.
 */
.editorial--indexed .prose__body :deep(> p:first-child) {
    font-family: var(--font-display);
    font-size: clamp(1.25rem, 1.7vw, 1.625rem);
    font-weight: 500;
    line-height: 1.55;
    /* `--tracking-heading` is already 0 under html[lang="ar"], so §10.3's
       rule against letter-spacing Arabic is kept by the token itself. */
    letter-spacing: var(--tracking-heading);
    color: var(--navy-900);
    max-inline-size: 46ch;
    text-wrap: pretty;
}
</style>
