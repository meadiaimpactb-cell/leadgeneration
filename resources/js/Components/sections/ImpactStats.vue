<script setup>
import { computed } from 'vue';
import Container from '@/Components/ui/Container.vue';
import Button from '@/Components/ui/Button.vue';
import ImpactStat from '@/Components/sections/ImpactStat.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/ImpactStats (§11.1) — the proof figures.
 *
 * Two presentations of the same block:
 *
 *  · `overlap` — a paper card riding 56px up over the hero's lower edge. The
 *    overlap is the point: it ties the claim in the headline to the evidence
 *    without a section break between them, and it is the first thing a buyer
 *    scrolling past the hero meets.
 *
 *  · `band` — a full navy section with the Sadu thread on its edge, used when
 *    the figures are the section rather than a footnote to the hero.
 *
 * The heading is optional in both. On the home page it is deliberately absent:
 * four large numbers under a hero need no one to announce them.
 */
const props = defineProps({
    heading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    items: { type: Array, default: () => [] },
    ctaLabel: { type: String, default: null },
    ctaUrl: { type: String, default: null },
    variant: {
        type: String,
        default: 'band',
        validator: (v) => ['band', 'overlap'].includes(v),
    },
    /** Already formatted server-side; month names are a locale's business. */
    measuredAt: { type: String, default: null },
});

const { root } = useReveal();
const { t } = useTranslation();

/** Four reads; five starts to look like a dashboard. */
const shown = computed(() => props.items.slice(0, 4));

/**
 * The column count is the number of figures, not a fixed four — three metrics
 * in a four-column grid leave a hole where the client can see something is
 * missing.
 */
const columns = computed(() => Math.max(1, shown.value.length));
</script>

<template>
    <section
        v-if="shown.length"
        ref="root"
        class="impact"
        :class="[`impact--${variant}`, variant === 'band' ? 'on-dark' : null]"
        :aria-labelledby="heading ? 'impact-heading' : undefined"
        :aria-label="heading ? undefined : eyebrow || undefined"
    >
        <!-- Sadu use 4 of 7: the vertical thread on a dark band's edge. -->
        <span v-if="variant === 'band'" class="sadu-edge sadu-weave impact__edge" aria-hidden="true" />

        <Container>
            <div class="impact__inner">
                <div v-if="eyebrow || heading || ctaLabel" class="impact__head">
                    <div>
                        <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
                        <h2 v-if="heading" id="impact-heading" class="h2 reveal">{{ heading }}</h2>
                    </div>

                    <Button v-if="ctaLabel" variant="secondary" :href="ctaUrl" class="impact__cta">
                        {{ ctaLabel }}
                    </Button>
                </div>

                <!-- A plain grid, not a <dl>: ImpactStat renders three
                     paragraphs per figure, which are not valid dl children. -->
                <div class="impact__grid" :style="{ '--impact-cols': columns }">
                    <ImpactStat
                        v-for="item in shown"
                        :key="item.id"
                        :value="item.value"
                        :suffix="item.suffix"
                        :label="item.label"
                        :note="item.note"
                        :metric-key="item.key"
                    />
                </div>

                <!--
                    A figure with no date is a claim; a figure with a date is a
                    measurement. This is derived from when the numbers were last
                    edited, so it cannot drift from them the way a second field
                    typed by hand would.
                -->
                <p v-if="measuredAt" class="impact__asof">
                    {{ t('impact.measured_at') }}: <span class="tabular">{{ measuredAt }}</span>
                </p>
            </div>
        </Container>
    </section>
</template>

<style scoped>
.impact__asof {
    margin-block-start: var(--s-6);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

/* On the navy band the muted paper grey would fall under AA. */
.impact--band .impact__asof {
    color: rgba(255, 255, 255, 0.62);
}

.impact {
    position: relative;
}

/* ---- overlap ---- */
/*
 * The card is pulled up over the hero. The negative margin is on the section,
 * not on the card, so the flow below it closes up by the same amount and no
 * gap opens under the hero.
 */
.impact--overlap {
    margin-block-start: calc(var(--s-8) * -1);
    z-index: 5;
}

/*
 * Octagon plus soft shadow on one element.
 *
 * `clip-path` cuts the box-shadow off with the corners, so the shadow moves to
 * a `drop-shadow` filter on the section — filters follow the clipped outline,
 * which is the only way to get both without a second wrapper element.
 */
.impact--overlap {
    filter: drop-shadow(0 24px 40px rgba(0, 37, 70, 0.16));
}

.impact--overlap .impact__inner {
    background: var(--paper-warm);
    clip-path: polygon(
        var(--cut-soft) 0, calc(100% - var(--cut-soft)) 0,
        100% var(--cut-soft), 100% calc(100% - var(--cut-soft)),
        calc(100% - var(--cut-soft)) 100%, var(--cut-soft) 100%,
        0 calc(100% - var(--cut-soft)), 0 var(--cut-soft)
    );
}

.impact--overlap .impact__grid > * {
    padding: var(--s-6) var(--s-5);
}

/*
 * Two by two on a phone, not four in a column: four stacked figures push the
 * rest of the page a screen and a half down, and the overlap that ties the
 * card to the hero stops being visible at all.
 */
@media (max-width: 639px) {
    .impact--overlap {
        margin-block-start: -20px;

        /*
         * The figure has to fit the half-cell it is now in.
         *
         * `--stat-size` is the hook ImpactStat already exposes, and nothing
         * had ever set it — so the figure sat on its 2.375rem fallback at
         * every width. Two columns of a 320px screen leave about 90px inside
         * the padding, and `14,401` sets at roughly 120px there. A number is
         * one unbreakable run, so it did not wrap: it ran under its
         * neighbour and was then sliced by the card's octagon clip.
         *
         * The clamp only bites where the cell is genuinely narrow; by 640px
         * it has returned to the design's size.
         */
        --stat-size: clamp(1.25rem, 7vw, 2.375rem);
    }

    .impact--overlap .impact__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* ---- band ---- */
.impact--band {
    padding-block: var(--section-y);
    background: var(--navy-900);
    overflow: hidden;
}

.impact--band .impact__edge {
    --sadu-tile: 24px;
    --sadu-edge-w: 24px;
    --sadu-colour: rgba(220, 173, 117, 0.55);
}

.impact__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--s-4);
    padding-block-end: var(--s-7);
    border-block-end: 1px solid var(--hairline-gold);
}

.impact--overlap .impact__head {
    padding: var(--s-6) var(--s-5) 0;
    border-block-end: 0;
}

.impact__cta {
    margin-inline-start: auto;
    white-space: nowrap;
    flex-shrink: 0;
    min-block-size: 52px;
}

.impact__grid {
    display: grid;
    grid-template-columns: 1fr;
    margin: 0;
}

.impact--band .impact__grid {
    gap: var(--s-7) 0;
    padding-block-start: var(--s-7);
}

/*
 * Gold rules between the figures. Written per breakpoint rather than once,
 * because the cell that must drop its rule is the first of each ROW, and how
 * many cells make a row changes.
 */
.impact__grid > * {
    border-inline-start: 1px solid var(--rule, var(--hairline));
    padding-inline: var(--s-5);
}

.impact--band .impact__grid > * {
    --rule: rgba(220, 173, 117, 0.55);
    padding-inline: var(--s-6);
}

.impact__grid > :first-child {
    border-inline-start: 0;
    padding-inline-start: 0;
}

.impact--overlap .impact__grid > :first-child {
    padding-inline-start: var(--s-5);
}

/*
 * The cell gives the figure back the width its padding was taking.
 *
 * Placed here rather than beside the two-column rule above, because it has
 * to override the first-child rule immediately preceding it — that one is a
 * three-class selector and a media query adds no specificity, so written
 * earlier it would have lost on source order and only three of the four
 * cells would have narrowed. 24px a side is a desktop gutter inside a 140px
 * box; 16px is the same rhythm at the size the box actually is.
 */
@media (max-width: 639px) {
    .impact--overlap .impact__grid > *,
    .impact--overlap .impact__grid > :first-child {
        padding-inline: var(--s-4);
    }
}

@media (min-width: 640px) {
    .impact__grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .impact__grid > :nth-child(odd) {
        border-inline-start: 0;
    }
}

@media (min-width: 1024px) {
    .impact--overlap {
        margin-block-start: -56px;
    }

    /* All the figures share one row, so only the true first cell drops its
       rule — the :nth-child(odd) rule from the previous breakpoint has to be
       undone explicitly, not merely overridden by source order. */
    .impact__grid {
        grid-template-columns: repeat(var(--impact-cols, 4), minmax(0, 1fr));
    }

    .impact__grid > :nth-child(odd) {
        border-inline-start: 1px solid var(--rule, var(--hairline));
    }

    .impact__grid > :first-child {
        border-inline-start: 0;
    }
}
</style>
