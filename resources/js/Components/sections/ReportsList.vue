<script setup>
import { computed, ref } from 'vue';
import Container from '@/Components/ui/Container.vue';
import Badge from '@/Components/ui/Badge.vue';
import ReportCover from '@/Components/ui/ReportCover.vue';
import { useReveal } from '@/Composables/useReveal';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * sections/ReportsList (§10.5) — publishable impact reports.
 *
 * This is the section a government buyer screenshots into their own file, so
 * every element here is governed by one rule: nothing may claim more than it
 * can prove.
 *
 *  - The year is shown once, from the year field. It used to be typed into the
 *    title as well, which is how a card ended up labelled 2025 wearing a 2023
 *    badge — two fields for one fact will disagree eventually.
 *  - A report with no file offers no download. A button that hands over an
 *    empty PDF costs more credibility than a missing button ever could, and
 *    this is the page where credibility is the product.
 */
const props = defineProps({
    heading: { type: String, default: null },
    items: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { root } = useReveal();

/** Newest first. The controller orders too; this keeps the filter honest. */
const sorted = computed(() =>
    [...props.items].sort((a, b) => (b.year ?? 0) - (a.year ?? 0))
);

const years = computed(() => [...new Set(sorted.value.map((r) => r.year).filter(Boolean))]);

/** The filter only earns its space once the list outgrows one row. */
const filterable = computed(() => sorted.value.length > 4 && years.value.length > 1);

const activeYear = ref(null);

const shown = computed(() =>
    activeYear.value === null
        ? sorted.value
        : sorted.value.filter((r) => r.year === activeYear.value)
);

/**
 * Sent to the analytics layer on download. A report download is the highest
 * quality signal of institutional interest this site can capture short of the
 * form itself, so it is a conversion, not a click.
 *
 * Guarded rather than assumed: no consent, no dataLayer, no throw.
 */
function recordDownload(report) {
    if (typeof window === 'undefined' || !Array.isArray(window.dataLayer)) {
        return;
    }

    window.dataLayer.push({
        event: 'report_download',
        report_slug: report.slug,
        report_year: report.year,
    });
}
</script>

<template>
    <!-- `id` is the hero's first action target: a public body arriving for
         the document should not have to read the page to reach it. -->
    <section v-if="items.length" id="reports" ref="root" class="section">
        <Container>
            <h2 v-if="heading" class="reveal">{{ heading }}</h2>

            <div v-if="filterable" class="years reveal">
                <button
                    type="button"
                    class="years__btn"
                    :class="{ 'years__btn--on': activeYear === null }"
                    :aria-pressed="activeYear === null"
                    @click="activeYear = null"
                >
                    {{ t('impact.all_years') }}
                </button>

                <button
                    v-for="year in years"
                    :key="year"
                    type="button"
                    class="years__btn tabular"
                    :class="{ 'years__btn--on': activeYear === year }"
                    :aria-pressed="activeYear === year"
                    @click="activeYear = year"
                >
                    {{ year }}
                </button>
            </div>

            <ul class="reports">
                <li v-for="report in shown" :key="report.id" class="card report reveal">
                    <ReportCover :cover="report.cover" :year="report.year" />

                    <Badge v-if="report.year" class="tabular">{{ report.year }}</Badge>
                    <h3 class="report__title">{{ report.title }}</h3>
                    <p v-if="report.summary" class="report__summary">{{ report.summary }}</p>

                    <a
                        v-if="report.fileUrl"
                        class="report__link link-weave"
                        :href="report.fileUrl"
                        download
                        @click="recordDownload(report)"
                    >
                        {{ t('impact.download') }}
                        <span class="report__meta latin">
                            {{ [report.fileLabel, report.fileSize].filter(Boolean).join(' · ') }}
                        </span>
                    </a>

                    <!--
                        Not a disabled button: there is nothing to press. The
                        card says where the report is, which is the truthful
                        state until Amad Craft uploads the file.
                    -->
                    <p v-else class="report__pending">{{ t('impact.report_pending') }}</p>
                </li>
            </ul>
        </Container>
    </section>
</template>

<style scoped>
.years {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-block-start: var(--s-5);
}

.years__btn {
    padding: var(--s-2) var(--s-4);
    border: 1px solid var(--hairline);
    background: transparent;
    color: var(--text-muted);
    font: inherit;
    font-size: var(--fs-sm);
    cursor: pointer;
    transition: color var(--dur-2) var(--ease), border-color var(--dur-2) var(--ease);
}

.years__btn--on {
    border-color: var(--navy-900);
    color: var(--navy-900);
    font-weight: 600;
}

.reports {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-7);
}

.report {
    align-items: flex-start;
}

.report__title {
    font-size: var(--fs-h3);
}

.report__summary {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.report__link {
    margin-block-start: auto;
    padding-block-start: var(--s-3);
    color: var(--link);
    font-size: var(--fs-sm);
    font-weight: 600;
}

.report__meta {
    color: var(--text-muted);
    font-weight: 400;
}

.report__pending {
    margin-block-start: auto;
    padding-block-start: var(--s-3);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 640px) {
    .reports {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .reports {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (prefers-reduced-motion: reduce) {
    .years__btn {
        transition: none;
    }
}
</style>
