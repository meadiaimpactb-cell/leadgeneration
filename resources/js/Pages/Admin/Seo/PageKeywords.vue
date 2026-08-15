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
 * The keywords a page is meant to be found by, and how well it serves each.
 *
 * ONE SCREEN, NOT TWO
 *
 * There used to be a second, site-wide list beside this one. Keeping both
 * meant entering the same phrases twice and remembering which screen answered
 * which question — and the people trained on this panel are not SEO staff.
 * This is the one that survived, because its answer is a list of edits.
 *
 * THE SHAPE IS BORROWED, DELIBERATELY
 *
 * Every keyword is a chip in a single block: the phrase, its score in colour,
 * a star that names the one the page is really about, and an × that removes
 * it. Adding is one field and the Enter key. That arrangement is what makes
 * the screen readable without training — the alternative, a table of rows with
 * a column of buttons, is the thing it replaced.
 *
 * Detail is one click and never in the way: selecting a chip opens what is
 * missing, in sentences, underneath.
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

const draft = ref('');
const processing = ref(false);
/** The chip whose detail is open, by id. */
const selected = ref(null);

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

const currentPage = computed(
    () => props.pages.find((p) => p.id === props.pageId)?.title ?? null
);

const primary = computed(() => props.keywords.find((k) => k.isPrimary) ?? null);

const counts = computed(() => ({
    strong: props.keywords.filter((k) => k.band === 'strong').length,
    medium: props.keywords.filter((k) => k.band === 'medium').length,
    weak: props.keywords.filter((k) => k.band === 'weak').length,
}));

const openKeyword = computed(
    () => props.keywords.find((k) => k.id === selected.value) ?? null
);

/**
 * Reloading with a different page or language is a GET, not local filtering:
 * the keywords for the other page are not in this payload, and pretending
 * otherwise would show an empty list that looks like "no keywords".
 */
function reload(patch) {
    router.get(
        '/admin/seo/keywords',
        { page_id: patch.pageId ?? props.pageId, locale: patch.locale ?? props.locale },
        { preserveState: false, preserveScroll: true }
    );
}

/**
 * Enter adds, which is what a field beside a list of chips promises.
 *
 * The text still goes to the server whole: it splits on commas and newlines,
 * so pasting a column from a spreadsheet keeps working and produces one chip
 * per phrase rather than one long chip.
 */
function add() {
    if (!draft.value.trim() || !props.pageId) return;

    processing.value = true;

    router.post(
        '/admin/seo/keywords',
        { page_id: props.pageId, locale: props.locale, terms: draft.value },
        {
            preserveScroll: true,
            onSuccess: () => (draft.value = ''),
            onFinish: () => (processing.value = false),
        }
    );
}

function remove(row) {
    if (selected.value === row.id) selected.value = null;

    router.delete(`/admin/seo/keywords/${row.id}`, { preserveScroll: true });
}

function makePrimary(row) {
    router.put(`/admin/seo/keywords/${row.id}/primary`, {}, { preserveScroll: true });
}

function reanalyse() {
    processing.value = true;

    router.post(
        '/admin/seo/keywords/reanalyse',
        { page_id: props.pageId, locale: props.locale },
        { preserveScroll: true, onFinish: () => (processing.value = false) }
    );
}

function toggle(row) {
    selected.value = selected.value === row.id ? null : row.id;
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
                    <!-- Page and language on one line: both are one choice, and
                         they are the context everything below belongs to. -->
                    <div class="picker">
                        <Field
                            :model-value="pageId"
                            :label="t('settings.page_keywords.page')"
                            type="select"
                            :options="pages.map((p) => ({ value: p.id, label: p.title }))"
                            @update:model-value="(v) => reload({ pageId: Number(v) })"
                        />

                        <div class="langs-wrap">
                            <span class="picker__label">{{ t('settings.page_keywords.language') }}</span>
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

                    <!-- One field, Enter to add. -->
                    <form class="add" @submit.prevent="add">
                        <input
                            v-model="draft"
                            class="add__input"
                            type="text"
                            :placeholder="t('settings.page_keywords.add_placeholder')"
                            :dir="locale === 'en' ? 'ltr' : null"
                            :aria-label="t('settings.page_keywords.bulk')"
                        />
                        <button class="btn btn--cta" type="submit" :disabled="processing || !draft.trim()">
                            {{ processing ? t('settings.page_keywords.analysing') : t('settings.page_keywords.add') }}
                        </button>
                    </form>

                    <p class="add__hint">{{ t('settings.page_keywords.add_hint') }}</p>

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

                    <p v-if="!keywords.length" class="empty">
                        {{ t('settings.page_keywords.none') }}
                        <span class="empty__help">{{ t('settings.page_keywords.empty_help') }}</span>
                    </p>

                    <template v-else>
                        <!-- Every keyword in one block. -->
                        <ul class="chips">
                            <li
                                v-for="row in keywords"
                                :key="row.id"
                                class="chip"
                                :class="[`is-${row.band}`, {
                                    'is-primary': row.isPrimary,
                                    'is-open': selected === row.id,
                                }]"
                            >
                                <button
                                    class="chip__star"
                                    type="button"
                                    :title="row.isPrimary
                                        ? t('settings.page_keywords.primary')
                                        : t('settings.page_keywords.make_primary')"
                                    :aria-label="row.isPrimary
                                        ? t('settings.page_keywords.primary')
                                        : t('settings.page_keywords.make_primary')"
                                    :aria-pressed="row.isPrimary"
                                    @click="makePrimary(row)"
                                >
                                    {{ row.isPrimary ? '★' : '☆' }}
                                </button>

                                <button class="chip__word" type="button" @click="toggle(row)">
                                    <span class="chip__text">{{ row.keyword }}</span>
                                    <span class="chip__score">{{ number(row.score) }}</span>
                                </button>

                                <span v-if="row.stuffed" class="chip__warn" :title="t('settings.page_keywords.stuffing')">⚠</span>
                                <span v-if="row.stale" class="chip__warn" :title="t('settings.page_keywords.stale')">↻</span>

                                <button
                                    class="chip__x"
                                    type="button"
                                    :title="t('settings.page_keywords.delete')"
                                    :aria-label="t('settings.page_keywords.delete')"
                                    @click="remove(row)"
                                >
                                    ×
                                </button>
                            </li>
                        </ul>

                        <div class="tally" role="group">
                            <span class="tally__cell tally__cell--strong">
                                {{ number(counts.strong) }} {{ t('settings.page_keywords.strong') }}
                            </span>
                            <span class="tally__cell tally__cell--medium">
                                {{ number(counts.medium) }} {{ t('settings.page_keywords.medium') }}
                            </span>
                            <span class="tally__cell tally__cell--weak">
                                {{ number(counts.weak) }} {{ t('settings.page_keywords.weak') }}
                            </span>

                            <button class="tally__btn" type="button" :disabled="processing" @click="reanalyse">
                                {{ t('settings.page_keywords.reanalyse') }}
                            </button>

                            <Link v-if="editUrl" class="link-weave" :href="editUrl">
                                {{ t('settings.page_keywords.edit_page') }}
                            </Link>
                        </div>

                        <!-- The detail of the selected chip, and nothing else. -->
                        <section v-if="openKeyword" class="detail" :class="`is-${openKeyword.band}`">
                            <header class="detail__head">
                                <span class="detail__word">{{ openKeyword.keyword }}</span>
                                <span class="bar" :title="`${openKeyword.score}/100`">
                                    <span class="bar__fill" :style="{ inlineSize: `${openKeyword.score}%` }" />
                                </span>
                                <span class="detail__score">{{ number(openKeyword.score) }}%</span>
                                <span class="detail__band">
                                    {{ t(`settings.page_keywords.${openKeyword.band}`) }}
                                </span>
                                <button class="chip__x" type="button" :aria-label="t('settings.page_keywords.hide_details')" @click="selected = null">×</button>
                            </header>

                            <p v-if="openKeyword.stale" class="note note--stale">
                                {{ t('settings.page_keywords.stale') }}
                            </p>

                            <p v-if="openKeyword.stuffed" class="note note--stuffed">
                                ⚠ {{ t('settings.page_keywords.stuffing') }}
                            </p>

                            <ul class="checks">
                                <li
                                    v-for="name in CHECKS"
                                    :key="name"
                                    class="check"
                                    :class="{ 'is-passed': passed(openKeyword, name) }"
                                >
                                    <span class="check__mark" aria-hidden="true">
                                        {{ passed(openKeyword, name) ? '✓' : '✗' }}
                                    </span>
                                    <span class="check__text">{{ advice(name, passed(openKeyword, name)) }}</span>
                                    <span class="check__weight">{{ number(weights[name] ?? 0) }}</span>
                                </li>
                            </ul>
                        </section>
                    </template>
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

.picker {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
    align-items: start;
}

.picker__label {
    display: block;
    margin-block-end: var(--s-2);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--navy-900);
}

.langs {
    display: flex;
    gap: var(--s-2);
}

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

.add {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

.add__input {
    flex: 1 1 18rem;
    min-inline-size: 0;
    min-block-size: 44px;
    padding: var(--s-2) var(--s-3);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    background: var(--paper);
    font: inherit;
    font-size: var(--fs-body);
}

.add__hint {
    margin-block: var(--s-2) var(--s-5);
    font-size: var(--fs-caption);
    color: var(--ink-600);
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

.subject.is-unset {
    border-inline-start-color: #B8860B;
}

/* One block, wrapping. The whole point of the redesign: the keywords are a
   set, and a set reads as chips rather than as rows in a table. */
.chips {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin: 0 0 var(--s-4);
    padding: 0;
    list-style: none;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: var(--s-1);
    padding-inline: var(--s-2);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    border-radius: 999px;
    /* The band colour rides on the leading edge, which is the right edge in
       Arabic and the left in English. */
    border-inline-start: 3px solid var(--hairline);
    max-inline-size: 100%;
}

.chip.is-weak { border-inline-start-color: #C0392B; }
.chip.is-medium { border-inline-start-color: #B8860B; }
.chip.is-strong { border-inline-start-color: #1E7A4B; }

.chip.is-primary {
    box-shadow: inset 0 0 0 2px var(--navy-900);
}

.chip.is-open {
    background: var(--sand-100, var(--paper-alt, #fff));
}

.chip__star,
.chip__x,
.chip__word {
    border: 0;
    background: none;
    font: inherit;
    cursor: pointer;
    color: inherit;
}

.chip__star {
    min-block-size: 36px;
    padding-inline: 2px;
    color: #B8860B;
    font-size: 1.05rem;
    line-height: 1;
}

.chip__word {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 36px;
    min-inline-size: 0;
    padding-inline: var(--s-1);
}

.chip__text {
    font-weight: 600;
    color: var(--navy-900);
    overflow-wrap: anywhere;
}

.chip__score {
    font-variant-numeric: tabular-nums;
    font-size: var(--fs-caption);
    color: var(--ink-600);
}

.chip__warn {
    font-size: var(--fs-caption);
    color: #7A5B12;
}

.chip__x {
    min-block-size: 36px;
    padding-inline: var(--s-2);
    color: #C0392B;
    font-size: 1.15rem;
    line-height: 1;
}

.tally {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
    margin-block-end: var(--s-5);
    font-size: var(--fs-caption);
}

.tally__cell {
    padding-inline-start: var(--s-2);
    border-inline-start: 3px solid var(--hairline);
    color: var(--ink-600);
    font-variant-numeric: tabular-nums;
}

.tally__cell--weak { border-inline-start-color: #C0392B; }
.tally__cell--medium { border-inline-start-color: #B8860B; }
.tally__cell--strong { border-inline-start-color: #1E7A4B; }

.tally__btn {
    min-block-size: 36px;
    padding-inline: var(--s-3);
    border: 0;
    background: none;
    color: var(--action-600);
    font: inherit;
    cursor: pointer;
    text-decoration: underline;
}

.detail {
    padding: var(--s-4);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    border-inline-start: 3px solid var(--hairline);
}

.detail.is-weak { border-inline-start-color: #C0392B; }
.detail.is-medium { border-inline-start-color: #B8860B; }
.detail.is-strong { border-inline-start-color: #1E7A4B; }

.detail__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
}

.detail__word {
    flex: 1 1 10rem;
    min-inline-size: 0;
    font-weight: 700;
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
}

.is-weak .bar__fill { background: #C0392B; }
.is-medium .bar__fill { background: #B8860B; }
.is-strong .bar__fill { background: #1E7A4B; }

.detail__score {
    font-variant-numeric: tabular-nums;
    font-weight: 700;
    color: var(--navy-900);
}

.detail__band {
    font-size: var(--fs-caption);
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
    background: var(--paper-alt, #fff);
    color: var(--ink-600);
    box-shadow: inset 0 0 0 1px var(--hairline);
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

@media (min-width: 768px) {
    .picker {
        grid-template-columns: 2fr 1fr;
    }
}
</style>
