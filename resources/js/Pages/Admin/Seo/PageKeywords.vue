<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Workspace from '@/Components/admin/Workspace.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * How well one page serves each of its keywords.
 *
 * The sibling of the site-wide keyword screen, which asks whether the site
 * says a phrase anywhere. This one asks whether *this page* is built around
 * it — and every line of the answer is one edit someone can make today.
 *
 * The three numbered steps are the layout, not decoration: the order the work
 * happens in is the order the fields appear in, so nobody has to be told what
 * to do first. Everything after the third step is automatic — the score is
 * written on entry and rewritten whenever the page is saved.
 *
 * No technical vocabulary reaches the screen. Every check reads as an
 * instruction: "the phrase is missing from the title — add it in the page
 * editor", never "meta_title: false".
 */
const props = defineProps({
    locales: { type: Array, default: () => [] },
    locale: { type: String, default: 'ar' },
    pages: { type: Array, default: () => [] },
    pageId: { type: Number, default: 0 },
    keywords: { type: Array, default: () => [] },
    weights: { type: Object, default: () => ({}) },
    bands: { type: Object, default: () => ({ weakBelow: 40, strongFrom: 70 }) },
    editUrl: { type: String, default: null },
});

const { t } = useTranslation();
const { number } = useFormat();

const terms = ref('');
const processing = ref(false);
const open = ref(null);

/** The page this list belongs to, named as the editor knows it. */
const currentPage = computed(
    () => props.pages.find((p) => p.id === props.pageId)?.title ?? null
);

/** The one phrase this page is for, if anybody has said which. */
const primary = computed(() => props.keywords.find((k) => k.isPrimary) ?? null);

/** The order the detail list is read in — effort ascending. */
const CHECKS = [
    'meta_title',
    'heading',
    'meta_description',
    'in_body_once',
    'in_body_twice',
    'first_paragraph',
    'image_alt',
    'slug',
];

const counts = computed(() => ({
    strong: props.keywords.filter((k) => k.band === 'strong').length,
    medium: props.keywords.filter((k) => k.band === 'medium').length,
    weak: props.keywords.filter((k) => k.band === 'weak').length,
}));

/**
 * Reloading with a different page or language is a GET, not local filtering:
 * the keywords for the other page are not in this payload, and pretending
 * otherwise would show an empty list that looks like "no keywords".
 */
function reload(patch) {
    router.get(
        '/admin/seo/page-keywords',
        { page_id: patch.pageId ?? props.pageId, locale: patch.locale ?? props.locale },
        { preserveState: false, preserveScroll: true }
    );
}

function analyse() {
    if (!terms.value.trim() || !props.pageId) return;

    processing.value = true;

    router.post(
        '/admin/seo/page-keywords',
        { page_id: props.pageId, locale: props.locale, terms: terms.value },
        {
            preserveScroll: true,
            onSuccess: () => (terms.value = ''),
            onFinish: () => (processing.value = false),
        }
    );
}

function reanalyse() {
    processing.value = true;

    router.post(
        '/admin/seo/page-keywords/reanalyse',
        { page_id: props.pageId, locale: props.locale },
        { preserveScroll: true, onFinish: () => (processing.value = false) }
    );
}

function remove(row) {
    router.delete(`/admin/seo/page-keywords/${row.id}`, { preserveScroll: true });
}

function makePrimary(row) {
    router.put(`/admin/seo/page-keywords/${row.id}/primary`, {}, { preserveScroll: true });
}

/**
 * The sentence for one check.
 *
 * The slug check is credited automatically in Arabic — page addresses are
 * written in English — so it gets its own line rather than a green tick with
 * no explanation, which would read as a bug.
 */
function advice(name, passed) {
    if (name === 'slug' && props.locale !== 'en') {
        return t('settings.page_keywords.check.slug.ar');
    }

    return t(`settings.page_keywords.check.${name}.${passed ? 'yes' : 'no'}`);
}

function passed(row, name) {
    return row.checks?.[name]?.passed === true;
}
</script>

<template>
    <AdminLayout :title="t('settings.page_keywords.title')">
        <Workspace>
            <Panel :title="t('settings.page_keywords.title')">
                <p class="intro">{{ t('settings.page_keywords.intro') }}</p>

                <p v-if="!pages.length" class="empty">{{ t('settings.page_keywords.no_pages') }}</p>

                <template v-else>
                    <!-- Step 1 and 2 side by side: both are one choice, and
                         separating them onto two rows makes the form look
                         longer than the work is. -->
                    <div class="steps">
                        <div class="step">
                            <span class="step__label">{{ t('settings.page_keywords.step_page') }}</span>

                            <Field
                                :model-value="pageId"
                                :label="t('settings.page_keywords.page')"
                                type="select"
                                :options="pages.map((p) => ({ value: p.id, label: p.title }))"
                                @update:model-value="(v) => reload({ pageId: Number(v) })"
                            />
                        </div>

                        <div class="step">
                            <span class="step__label">{{ t('settings.page_keywords.step_locale') }}</span>

                            <!-- Its own label, matching the select's, so the
                                 two controls sit on one line at one height.
                                 Without it the buttons ride up against the
                                 label opposite them. -->
                            <span class="pick__label">{{ t('settings.page_keywords.language') }}</span>

                            <div class="langs" role="group">
                                <button
                                    v-for="code in locales"
                                    :key="code"
                                    class="lang"
                                    :class="{ 'is-active': code === locale }"
                                    type="button"
                                    @click="reload({ locale: code })"
                                >
                                    {{ t(`settings.page_keywords.locale_${code}`) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="step step--terms">
                        <span class="step__label">{{ t('settings.page_keywords.step_terms') }}</span>
                        <Field
                            v-model="terms"
                            :label="t('settings.page_keywords.bulk')"
                            :hint="t('settings.page_keywords.bulk_hint')"
                            type="textarea"
                            :rows="5"
                            :dir="locale === 'en' ? 'ltr' : null"
                        />
                    </div>

                    <div class="actions">
                        <button class="btn btn--cta" type="button" :disabled="processing" @click="analyse">
                            {{ processing
                                ? t('settings.page_keywords.analysing')
                                : t('settings.page_keywords.analyse') }}
                        </button>

                        <button
                            v-if="keywords.length"
                            class="btn btn--ghost"
                            type="button"
                            :disabled="processing"
                            @click="reanalyse"
                        >
                            {{ t('settings.page_keywords.reanalyse') }}
                        </button>

                        <Link v-if="editUrl" class="link-weave" :href="editUrl">
                            {{ t('settings.page_keywords.edit_page') }}
                        </Link>
                    </div>

                    <!-- The page's subject, stated before its scores.
                         Somebody opening this screen should learn what the
                         page is meant to rank for before they read how well
                         it does — and if nobody has said, that is the first
                         thing to fix. -->
                    <p class="subject" :class="{ 'is-unset': !primary }">
                        <template v-if="primary">
                            {{ t('settings.page_keywords.subject_is', {
                                page: currentPage,
                                keyword: primary.keyword,
                            }) }}
                        </template>
                        <template v-else>
                            {{ t('settings.page_keywords.subject_unset') }}
                        </template>
                    </p>

                    <!-- Three counts, strongest first, in the same order as
                         the list below them. -->
                    <div class="tally" role="group">
                        <span class="tally__cell tally__cell--strong">
                            <span class="tally__n">{{ number(counts.strong) }}</span>
                            <span class="tally__l">{{ t('settings.page_keywords.strong') }}</span>
                        </span>
                        <span class="tally__cell tally__cell--medium">
                            <span class="tally__n">{{ number(counts.medium) }}</span>
                            <span class="tally__l">{{ t('settings.page_keywords.medium') }}</span>
                        </span>
                        <span class="tally__cell tally__cell--weak">
                            <span class="tally__n">{{ number(counts.weak) }}</span>
                            <span class="tally__l">{{ t('settings.page_keywords.weak') }}</span>
                        </span>
                    </div>

                    <p v-if="!keywords.length" class="empty">
                        {{ t('settings.page_keywords.none') }}
                        <span class="empty__help">{{ t('settings.page_keywords.empty_help') }}</span>
                    </p>

                    <ul v-else class="rows">
                        <li
                            v-for="row in keywords"
                            :key="row.id"
                            class="row"
                            :class="[`is-${row.band}`, { 'is-primary': row.isPrimary }]"
                        >
                            <div class="row__head">
                                <span class="row__word">
                                    <span v-if="row.isPrimary" class="star" aria-hidden="true">★</span>
                                    {{ row.keyword }}
                                </span>

                                <span v-if="row.isPrimary" class="flag flag--primary">
                                    {{ t('settings.page_keywords.primary') }}
                                </span>

                                <!-- The bar is the whole point: a number alone
                                     is read as a grade, a bar is read as a
                                     distance left to travel. -->
                                <span class="bar" :title="`${row.score}/100`">
                                    <span class="bar__fill" :style="{ inlineSize: `${row.score}%` }" />
                                </span>

                                <span class="row__score">{{ number(row.score) }}%</span>

                                <span class="row__band">
                                    {{ t(`settings.page_keywords.${row.band}`) }}
                                </span>

                                <!-- Both flags are warnings, not scores: the
                                     bar behind them is still the answer. -->
                                <span v-if="row.stuffed" class="flag flag--stuffed">
                                    ⚠ {{ t('settings.page_keywords.stuffing_short') }}
                                </span>

                                <span v-if="row.stale" class="flag flag--stale">
                                    {{ t('settings.page_keywords.stale_short') }}
                                </span>

                                <button
                                    v-if="!row.isPrimary"
                                    class="row__btn"
                                    type="button"
                                    @click="makePrimary(row)"
                                >
                                    {{ t('settings.page_keywords.make_primary') }}
                                </button>

                                <button
                                    class="row__btn"
                                    type="button"
                                    @click="open = open === row.id ? null : row.id"
                                >
                                    {{ open === row.id
                                        ? t('settings.page_keywords.hide_details')
                                        : t('settings.page_keywords.details') }}
                                </button>

                                <button class="row__btn row__btn--del" type="button" @click="remove(row)">
                                    {{ t('settings.page_keywords.delete') }}
                                </button>
                            </div>

                            <template v-if="open === row.id">
                                <p v-if="row.stale" class="note note--stale">
                                    {{ t('settings.page_keywords.stale') }}
                                </p>

                                <!-- Placed above the checks, not among them:
                                     it carries no weight, and a line in the
                                     scored list with no number beside it reads
                                     as a check that failed silently. -->
                                <p v-if="row.stuffed" class="note note--stuffed">
                                    ⚠ {{ t('settings.page_keywords.stuffing') }}
                                </p>
                            </template>

                            <ul v-if="open === row.id" class="checks">
                                <li
                                    v-for="name in CHECKS"
                                    :key="name"
                                    class="check"
                                    :class="{ 'is-passed': passed(row, name) }"
                                >
                                    <span class="check__mark" aria-hidden="true">
                                        {{ passed(row, name) ? '✓' : '✗' }}
                                    </span>
                                    <span class="check__text">{{ advice(name, passed(row, name)) }}</span>
                                    <span class="check__weight">{{ number(weights[name] ?? 0) }}</span>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </template>
            </Panel>
        </Workspace>
    </AdminLayout>
</template>

<style scoped>
.intro {
    margin-block-end: var(--s-5);
    color: var(--ink-600);
    line-height: var(--lh-body);
}

.steps {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

.step__label {
    display: block;
    margin-block-end: var(--s-2);
    font-weight: 700;
    font-size: var(--fs-caption);
    color: var(--navy-900);
}

.step--terms {
    margin-block-start: var(--s-4);
}

.langs {
    display: flex;
    gap: var(--s-2);
}

/* Two buttons rather than a select: there are exactly two languages and both
   must be visible without opening anything. */
.lang {
    min-block-size: 44px;
    padding-inline: var(--s-5);
    border: 1px solid var(--hairline);
    background: var(--paper);
    color: var(--ink-600);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.lang.is-active {
    border-color: var(--navy-900);
    background: var(--navy-900);
    color: #fff;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
    margin-block: var(--s-5);
}

.tally {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: repeat(3, 1fr);
    margin-block-end: var(--s-5);
}

.tally__cell {
    display: flex;
    flex-direction: column;
    gap: var(--s-1);
    padding: var(--s-4);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    /* The colour is on the leading edge, which is the right edge in Arabic
       and the left in English — hence the logical property. */
    border-inline-start: 3px solid var(--hairline);
}

.tally__cell--weak { border-inline-start-color: #C0392B; }
.tally__cell--medium { border-inline-start-color: #B8860B; }
.tally__cell--strong { border-inline-start-color: #1E7A4B; }

.tally__n {
    font-size: 1.5rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--navy-900);
}

.tally__l {
    font-size: var(--fs-caption);
    color: var(--ink-600);
}

.empty {
    padding: var(--s-5);
    background: var(--paper);
    color: var(--ink-600);
}

.empty__help {
    display: block;
    margin-block-start: var(--s-2);
    font-size: var(--fs-caption);
}

.rows {
    display: grid;
    gap: var(--s-3);
    list-style: none;
    margin: 0;
    padding: 0;
}

.row {
    padding: var(--s-4);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    border-inline-start: 3px solid var(--hairline);
}

.row.is-weak { border-inline-start-color: #C0392B; }
.row.is-medium { border-inline-start-color: #B8860B; }
.row.is-strong { border-inline-start-color: #1E7A4B; }

.row__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
}

/* A very long phrase wraps instead of pushing the bar and the buttons out of
   the panel — the row grows downward, never sideways. */
.row__word {
    flex: 1 1 12rem;
    min-inline-size: 0;
    font-weight: 600;
    color: var(--navy-900);
    overflow-wrap: anywhere;
}

.bar {
    flex: 0 0 8rem;
    block-size: 10px;
    background: var(--hairline);
    overflow: hidden;
}

.bar__fill {
    display: block;
    block-size: 100%;
    background: currentColor;
}

.is-weak .bar__fill { background: #C0392B; }
.is-medium .bar__fill { background: #B8860B; }
.is-strong .bar__fill { background: #1E7A4B; }

.row__score {
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    color: var(--navy-900);
}

.row__band {
    font-size: var(--fs-caption);
    color: var(--ink-600);
}

/* Warnings, deliberately quieter than the band label beside them: neither of
   these changes the score, and one that shouted would be read as one that did. */
.flag {
    padding-block: 2px;
    padding-inline: var(--s-2);
    font-size: var(--fs-caption);
    font-weight: 600;
    overflow-wrap: anywhere;
}

.flag--stuffed {
    background: #FBF0DA;
    color: #7A5B12;
}

.flag--stale {
    background: var(--hairline);
    color: var(--ink-600);
}

.note {
    margin-block-start: var(--s-3);
    padding: var(--s-3);
    font-size: var(--fs-caption);
    line-height: var(--lh-body);
}

.note--stuffed {
    background: #FBF0DA;
    color: #7A5B12;
}

.note--stale {
    background: var(--sand-100, var(--paper));
    color: var(--ink-600);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

/* Matches Field's own label, whose styles are scoped to that component and
   cannot be borrowed. Keeping the two identical is what puts the language
   buttons and the page select on one line at one height. */
.pick__label {
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--navy-900);
}

.subject {
    margin-block-end: var(--s-4);
    padding: var(--s-3) var(--s-4);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    border-inline-start: 3px solid var(--navy-900);
    line-height: var(--lh-body);
    color: var(--ink-600);
}

/* A page with no declared subject is a question, not a statement. */
.subject.is-unset {
    border-inline-start-color: #B8860B;
}

.star {
    color: #B8860B;
}

.flag--primary {
    background: var(--navy-900);
    color: #fff;
}

/* The subject of the page reads as the heading of its own list. */
.row.is-primary {
    box-shadow: inset 0 0 0 2px var(--navy-900);
}

.row__btn {
    min-block-size: 44px;
    padding-inline: var(--s-3);
    border: 0;
    background: none;
    color: var(--action-600);
    font: inherit;
    cursor: pointer;
    text-decoration: underline;
}

.row__btn--del {
    color: #C0392B;
}

.checks {
    display: grid;
    gap: var(--s-2);
    margin-block-start: var(--s-4);
    padding-block-start: var(--s-4);
    border-block-start: 1px solid var(--hairline);
    list-style: none;
}

.check {
    display: flex;
    align-items: flex-start;
    gap: var(--s-3);
    line-height: var(--lh-body);
}

.check__mark {
    flex: 0 0 auto;
    font-weight: 700;
    color: #C0392B;
}

.check.is-passed .check__mark {
    color: #1E7A4B;
}

.check__text {
    flex: 1 1 auto;
    color: var(--ink-600);
}

.check__weight {
    flex: 0 0 auto;
    font-variant-numeric: tabular-nums;
    font-size: var(--fs-caption);
    color: var(--ink-400, var(--ink-600));
}

@media (min-width: 768px) {
    .steps {
        grid-template-columns: 2fr 1fr;
        align-items: start;
    }
}
</style>
