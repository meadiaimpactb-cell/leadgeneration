<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Container from '@/Components/ui/Container.vue';
import SectionIndex from '@/Components/ui/SectionIndex.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * sections/SolutionsGrid (§11.1) — the service lines.
 *
 * A ruled 2×2, not a card grid. Cards with shadows read as a product listing,
 * which is the wrong signal for a site that sells nothing (§2.2); quadrants
 * divided by hairlines read as a capability statement, which is what these
 * are.
 *
 * The rules are drawn positionally — a cell carries an end-rule unless it is
 * last in its row, and a bottom rule unless it is in the last row — so the
 * grid closes correctly for three items or for seven, not only for four.
 */
const props = defineProps({
    heading: { type: String, default: null },
    eyebrow: { type: String, default: null },
    /** The short line to the side of the heading. */
    note: { type: String, default: null },
    items: { type: Array, default: () => [] },
    index: { type: Number, default: null },
    total: { type: Number, default: null },
    slug: { type: String, default: null },
});

const { t } = useTranslation();
const { root } = useReveal();
const { number } = useFormat();

const COLUMNS = 2;

/**
 * Which rules a given cell draws. Computed rather than written as
 * `:nth-child(1)`-style rules so the borders stay correct at any count.
 */
function edges(i) {
    const lastInRow = i % COLUMNS === COLUMNS - 1;
    const inLastRow = i >= props.items.length - ((props.items.length % COLUMNS) || COLUMNS);

    return {
        'solution--rule-end': !lastInRow,
        'solution--rule-block': !inLastRow,
    };
}

const rank = (i) => number(i + 1).padStart(2, '0');

const hasHead = computed(() => Boolean(props.eyebrow || props.heading || props.note || props.index));
</script>

<template>
    <section
        v-if="items.length"
        ref="root"
        class="solutions"
        :aria-labelledby="heading ? 'solutions-heading' : undefined"
    >
        <Container>
            <div v-if="hasHead" class="solutions__head">
                <div>
                    <SectionIndex :index="index" :total="total" :slug="slug" />
                    <span v-if="eyebrow" class="eyebrow reveal">{{ eyebrow }}</span>
                    <h2 v-if="heading" id="solutions-heading" class="h2 solutions__title reveal">
                        {{ heading }}
                    </h2>
                </div>

                <p v-if="note" class="solutions__note">{{ note }}</p>
            </div>

            <ul class="solutions__grid">
                <li v-for="(item, i) in items" :key="item.id" class="reveal">
                    <!-- The whole quadrant is one link: a separate "read more"
                         gives keyboard users two stops for one destination. -->
                    <Link :href="item.url" class="solution" :class="edges(i)">
                        <NavIcon
                            v-if="item.icon"
                            :name="item.icon"
                            class="solution__icon"
                            :size="40"
                            :weight="1.3"
                            :muted="false"
                        />

                        <span class="solution__body">
                            <span class="mono-label mono-label--gold mono-label--tight">
                                {{ rank(i) }}
                            </span>
                            <h3 class="solution__name">{{ item.name }}</h3>
                            <p v-if="item.summary" class="solution__summary">{{ item.summary }}</p>

                            <span class="solution__more">
                                {{ t('common.learn_more') }}
                                <span class="arrow" aria-hidden="true">&#8594;</span>
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.solutions {
    padding-block: var(--section-y) 0;
}

.solutions__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: var(--s-5);
    padding-block-end: var(--s-6);
    border-block-end: 1px solid var(--hairline);
}

.solutions__title {
    margin-block-start: var(--s-4);
    font-size: clamp(1.75rem, 3vw, 2.75rem);
}

.solutions__note {
    margin-inline-start: auto;
    font-size: var(--fs-sm);
    color: var(--text-muted);
    max-inline-size: 34ch;
}

.solutions__grid {
    display: grid;
    grid-template-columns: 1fr;
}

.solution {
    display: flex;
    align-items: flex-start;
    gap: var(--s-5);
    block-size: 100%;
    padding-block: var(--s-8);
    color: var(--text);
    transition: background-color var(--dur-micro) var(--ease);
}

.solution:hover {
    background: var(--sand);
}

.solution:focus-visible {
    outline: 2px solid var(--gold-400);
    outline-offset: -2px;
}

.solution--rule-block {
    border-block-end: 1px solid var(--hairline);
}

.solution__icon {
    flex-shrink: 0;
    color: var(--navy-900);
}

.solution__body {
    display: block;
    min-inline-size: 0;
}

.solution__name {
    margin-block-start: var(--s-2);
    font-size: 1.4375rem;
    line-height: var(--lh-heading);
}

.solution__summary {
    margin-block-start: var(--s-3);
    font-size: var(--fs-body);
    line-height: var(--lh-body);
    color: var(--text-muted);
    max-inline-size: 42ch;
}

.solution__more {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    margin-block-start: var(--s-5);
    color: var(--action-600);
    font-size: var(--fs-sm);
    font-weight: 600;
}

.solution:hover .arrow,
.solution:focus-visible .arrow {
    transform: translateX(4px);
}

html[dir='rtl'] .solution:hover .arrow,
html[dir='rtl'] .solution:focus-visible .arrow {
    transform: scaleX(-1) translateX(4px);
}

@media (min-width: 900px) {
    .solutions__grid {
        grid-template-columns: repeat(2, 1fr);
    }

    /*
     * The inner rule and the padding that keeps text off it. Written as
     * logical properties so the quadrants swap sides with the document and
     * the outer edges of the grid stay flush with the container.
     */
    .solution {
        padding-block: var(--s-8);
        padding-inline-end: var(--s-7);
    }

    .solution--rule-end {
        border-inline-end: 1px solid var(--hairline);
    }

    .solutions__grid > :nth-child(even) .solution {
        padding-inline: var(--s-7) 0;
    }
}
</style>
