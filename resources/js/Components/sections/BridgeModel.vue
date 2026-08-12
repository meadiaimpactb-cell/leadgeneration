<script setup>
import { usePage } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import NodeMark from '@/Components/ui/NodeMark.vue';
import { useReveal } from '@/Composables/useReveal';
import { useFormat } from '@/Composables/useFormat';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/BridgeModel — the one picture of the whole business model.
 *
 * /about replaced its "how we work" paragraph with this. That paragraph was
 * the operational process, which every solutions page already carries; a
 * visitor reaching the about page has stopped asking "how will you run my
 * order" and started asking "what are you, exactly". This answers that: two
 * parties who cannot reach each other, and the distance Amad Craft takes on
 * between them.
 *
 * Drawn, not photographed, and drawn from the parts this identity already
 * owns — the octagon node, the gold thread, the mono numbering. The glyphs
 * are inline SVG so they take their colour from a token and stay sharp at any
 * size; everything ELSE is HTML and CSS rather than one monolithic SVG, for
 * three reasons that matter more here than draughtsmanship does:
 *
 *  · the labels are real text, so they translate from the panel, are read by
 *    a screen reader in order, and can be selected and searched;
 *  · the layout is a grid, so it turns from a horizontal span into a vertical
 *    ladder at a breakpoint instead of scaling one fixed drawing down to
 *    illegibility on a phone;
 *  · logical properties mean the whole span reverses in LTR with no mirrored
 *    rule and no second artwork.
 *
 * Everything it renders comes from the section's `settings` (§22.1). With no
 * nodes configured it renders nothing at all.
 */
const props = defineProps({
    heading: { type: String, default: null },
    subheading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    /** `{ title, title_en, body, body_en }` — the party at each end. */
    start: { type: Object, default: null },
    end: { type: Object, default: null },
    /** What sits between them, i.e. us. */
    middle: { type: Object, default: null },
    /** The stages of the distance: `[{ title, title_en }]`. */
    items: { type: Array, default: () => [] },
});

const { root } = useReveal();
const { number } = useFormat();
const { t } = useTranslation();
const page = usePage();

/**
 * A label in the reader's language.
 *
 * `settings` is one JSON column and is NOT per-locale, so repeatable text
 * inside it carries both languages on each item. There is no fallback to the
 * Arabic key: §12 forbids serving Arabic to an English reader, so a missing
 * English string renders nothing and the diagram is shorter rather than
 * bilingual.
 */
function text(item, field) {
    if (!item) return null;

    return page.props.locale === 'en' ? (item[`${field}_en`] ?? null) : (item[field] ?? null);
}

const rank = (i) => number(i + 1).padStart(2, '0');
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="section bridge"
        :aria-labelledby="heading ? 'bridge-heading' : undefined"
    >
        <Container>
            <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
            <h2 v-if="heading" id="bridge-heading" class="h2 bridge__title reveal">{{ heading }}</h2>
            <p v-if="subheading" class="bridge__sub reveal">{{ subheading }}</p>

            <figure class="bridge__figure reveal">
                <!--
                    Named as a diagram for anyone who cannot see it. The
                    contents below are real text in reading order, so this is
                    a label rather than a substitute description — a screen
                    reader gets the model itself, not a paraphrase of it.
                -->
                <p class="visually-hidden">{{ t('common.diagram') }}</p>

                <div class="bridge__span">
                    <div v-if="start" class="bridge__end cut-framed">
                        <div class="bridge__end-inner cut">
                            <h3 class="bridge__end-title">{{ text(start, 'title') }}</h3>
                            <p v-if="text(start, 'body')" class="bridge__end-body">
                                {{ text(start, 'body') }}
                            </p>
                        </div>
                    </div>

                    <div class="bridge__middle">
                        <p v-if="text(middle, 'title')" class="bridge__middle-title">
                            {{ text(middle, 'title') }}
                        </p>

                        <div class="bridge__rail-wrap">
                            <!-- Sadu use 8 of 8: the thread the stages sit on.
                                 Added to the closed vocabulary in
                                 components.css, not invented at this call
                                 site. -->
                            <span class="sadu-rail sadu-weave" aria-hidden="true" />

                            <ol class="bridge__nodes" :style="{ '--bridge-cols': items.length }">
                                <li v-for="(node, i) in items" :key="i" class="bridge__node">
                                    <NodeMark class="bridge__glyph" />

                                    <span class="mono-label mono-label--tight bridge__rank">
                                        {{ rank(i) }}
                                    </span>
                                    <span class="bridge__label">{{ text(node, 'title') }}</span>
                                </li>
                            </ol>
                        </div>

                        <p v-if="text(middle, 'body')" class="bridge__middle-body">
                            {{ text(middle, 'body') }}
                        </p>
                    </div>

                    <div v-if="end" class="bridge__end cut-framed">
                        <div class="bridge__end-inner cut">
                            <h3 class="bridge__end-title">{{ text(end, 'title') }}</h3>
                            <p v-if="text(end, 'body')" class="bridge__end-body">
                                {{ text(end, 'body') }}
                            </p>
                        </div>
                    </div>
                </div>
            </figure>
        </Container>
    </section>
</template>

<style scoped>
.bridge__title {
    margin-block-start: var(--s-4);
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

.bridge__sub {
    margin-block-start: var(--s-4);
    max-inline-size: 62ch;
    color: var(--text-muted);
    font-size: var(--fs-body-lg);
}

.bridge__figure {
    margin: var(--s-8) 0 0;
}

/*
 * Mobile: a vertical ladder — artisan, the five stages, institution. The
 * horizontal span below is the enhancement, not the base, because a diagram
 * that only works at 1440 is a picture of a diagram.
 */
.bridge__span {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-6);
    align-items: stretch;
}

.bridge__end {
    /* The gold hairline is a layer behind the cut box: clip-path removes a
       border along with the corner, so the edge cannot be a declaration. */
    align-self: stretch;
}

.bridge__end-inner {
    block-size: 100%;
    padding: var(--s-6);
    background: var(--paper);
}

.bridge__end-title {
    font-size: var(--fs-h3);
    color: var(--navy-900);
}

.bridge__end-body {
    margin-block-start: var(--s-2);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.bridge__middle {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.bridge__middle-title,
.bridge__middle-body {
    text-align: center;
}

.bridge__middle-title {
    margin-block-end: var(--s-5);
    font-weight: 600;
    color: var(--navy-900);
}

.bridge__middle-body {
    margin-block-start: var(--s-5);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.bridge__rail-wrap {
    position: relative;
    padding-inline-start: 18px;
}

/* Vertical thread on the ladder; it becomes horizontal at the wide
   breakpoint. Both are the same tiled weave, so one declaration sets the
   scale for either orientation. */
.sadu-rail {
    position: absolute;
    inset-block: 8px;
    inset-inline-start: 0;
    inline-size: 8px;
    --sadu-tile: 8px;
    --sadu-colour: var(--gold-400);
    opacity: 0.9;
    pointer-events: none;
}

.bridge__nodes {
    display: grid;
    gap: var(--s-5);
    list-style: none;
}

.bridge__node {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding-inline-start: var(--s-5);
}

/* The node sits ON the thread, straddling it. */
.bridge__glyph {
    position: absolute;
    inset-inline-start: -14px;
    inline-size: 28px;
    block-size: 28px;
    color: var(--gold-400);
    flex: 0 0 auto;
}

.bridge__rank {
    color: var(--action-600);
}

.bridge__label {
    font-weight: 600;
    color: var(--navy-900);
}

@media (min-width: 1024px) {
    /* The span: both parties flanking the distance between them. `auto` on
       the ends and `1fr` in the middle is the whole argument of the section —
       the middle is what there is most of. */
    .bridge__span {
        grid-template-columns: minmax(200px, 0.9fr) minmax(0, 1.9fr) minmax(200px, 0.9fr);
        gap: var(--s-6);
        align-items: center;
    }

    .bridge__rail-wrap {
        padding-inline-start: 0;
        padding-block-start: 22px;
    }

    .sadu-rail {
        inset-block: auto;
        inset-block-start: 8px;
        inset-inline: 6%;
        inline-size: auto;
        block-size: 8px;
    }

    .bridge__nodes {
        /* One column per stage, from the count the panel configured — the
           same device ImpactStats uses, and for the same reason: a fixed
           five-column grid leaves a hole the day someone adds a sixth. */
        grid-template-columns: repeat(var(--bridge-cols, 5), minmax(0, 1fr));
        gap: var(--s-3);
    }

    .bridge__node {
        flex-direction: column;
        align-items: center;
        gap: var(--s-1);
        padding-inline-start: 0;
        padding-block-start: var(--s-5);
        text-align: center;
    }

    .bridge__glyph {
        inset-inline-start: auto;
        inset-block-start: -14px;
    }

    .bridge__label {
        font-size: var(--fs-sm);
    }
}
</style>
