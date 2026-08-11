<script setup>
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import SectionIndex from '@/Components/ui/SectionIndex.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * sections/SectorSpotlight (§11.1) — the four audience segments
 * (government / private / partners / artisans).
 *
 * An editorial list, not a card grid. Four cards of near-identical length
 * flatten the segments into interchangeable tiles, and §3 is explicit that
 * they are ranked: government first, artisans fourth. Ruled rows carry that
 * ordering visually, give each row room for a real sentence, and read as a
 * considered statement of who the company serves rather than as a menu.
 *
 * Order comes from `sort_order`, which the client controls; the priority in
 * §3 is the default the seeder sets, not something hard-coded here.
 */
defineProps({
    heading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    items: { type: Array, default: () => [] },
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    slug: { type: String, default: null },
});

const { root } = useReveal();
const { t } = useTranslation();
const { number } = useFormat();

const rank = (i) => number(i + 1).padStart(2, '0');
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="section sectors"
        :aria-labelledby="heading ? 'sectors-heading' : undefined"
    >
        <Container>
            <SectionIndex :index="index" :total="total" :slug="slug" />
            <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
            <h2 v-if="heading" id="sectors-heading" class="h2 sectors__title reveal">
                {{ heading }}
            </h2>

            <ol class="sectors__list">
                <li v-for="(item, i) in items" :key="item.id" class="sector reveal">
                    <Link :href="item.url" class="sector__link">
                        <span class="mono-label mono-label--gold sector__index" aria-hidden="true">
                            {{ rank(i) }}
                        </span>

                        <h3 class="sector__name">{{ item.name }}</h3>

                        <p v-if="item.summary" class="sector__summary">{{ item.summary }}</p>

                        <span class="sector__go" aria-hidden="true">
                            <span class="arrow">&#8594;</span>
                        </span>

                        <span class="visually-hidden">{{ t('common.learn_more') }}</span>
                    </Link>
                </li>
            </ol>
        </Container>
    </section>
</template>

<style scoped>
.sectors__title {
    margin-block-start: var(--s-4);
    margin-block-end: var(--s-8);
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

.sectors__list {
    list-style: none;
}

/* One rule above each row, and one closing the list — so adjacent rows share
   a single line rather than stacking two. */
.sector {
    border-block-start: 1px solid var(--hairline);
}

.sector:last-child {
    border-block-end: 1px solid var(--hairline);
}

.sector__link {
    display: grid;
    grid-template-columns: auto 1fr auto;
    grid-template-areas:
        'index name go'
        '.     summary summary';
    align-items: center;
    gap: var(--s-2) var(--s-5);
    padding: var(--s-5) var(--s-2);
    color: var(--text);
    transition: background-color var(--dur-micro) var(--ease);
}

/* No shadow, no lift: the row tints and nothing moves. */
.sector__link:hover {
    background: var(--sand);
}

.sector__link:focus-visible {
    outline: 2px solid var(--gold-400);
    outline-offset: -2px;
}

.sector__index {
    grid-area: index;
    font-size: 0.9375rem;
    font-weight: 600;
    min-inline-size: 3ch;
}

.sector__name {
    grid-area: name;
    font-size: 1.3125rem;
    font-weight: 700;
    line-height: var(--lh-heading);
}

.sector__summary {
    grid-area: summary;
    font-size: var(--fs-body);
    line-height: var(--lh-body);
    color: var(--text-muted);
}

.sector__go {
    grid-area: go;
    color: var(--action-600);
    font-size: 1.375rem;
}

.sector__link:hover .arrow,
.sector__link:focus-visible .arrow {
    transform: translateX(4px);
}

html[dir='rtl'] .sector__link:hover .arrow,
html[dir='rtl'] .sector__link:focus-visible .arrow {
    transform: scaleX(-1) translateX(4px);
}

@media (min-width: 900px) {
    /*
     * One row per segment: rank, name, sentence, arrow. The 1fr/1.5fr split
     * is what stops a two-word name and a full sentence from being given the
     * same width.
     */
    .sector__link {
        grid-template-columns: 96px 1fr 1.5fr auto;
        grid-template-areas: 'index name summary go';
        gap: var(--s-6);
        padding: var(--s-6) var(--s-3);
    }
}
</style>
