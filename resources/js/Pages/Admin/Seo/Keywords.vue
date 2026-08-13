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
 * Target keywords, and whether the site actually covers them (§13).
 *
 * Entry is one textarea: paste any number of phrases, separated however they
 * arrived. No page has to be chosen — you learn what buyers search for before
 * you know which page should answer it.
 *
 * The list below it is the real product. For each term it shows which
 * published pages contain it and whether any of them carries it in the title.
 * A term matching nothing is a gap, shown first, because that is the only
 * thing on this screen an editor can act on: write the page, and the ranking
 * follows. The keywords box alone changes nothing, and the screen says so.
 */
const props = defineProps({
    locales: { type: Array, default: () => [] },
    report: { type: Object, default: () => ({}) },
    groups: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { number } = useFormat();

const locale = ref(props.locales[0] ?? 'ar');
const terms = ref('');
const group = ref('');
const processing = ref(false);
const filter = ref('all');

const rows = computed(() => props.report[locale.value] ?? []);

const gaps = computed(() => rows.value.filter((r) => !r.covered));
const weak = computed(() => rows.value.filter((r) => r.covered && !r.strong));
const strong = computed(() => rows.value.filter((r) => r.strong));

const shown = computed(() => {
    if (filter.value === 'gaps') return gaps.value;
    if (filter.value === 'weak') return weak.value;
    if (filter.value === 'strong') return strong.value;

    // Gaps first: the list is a worklist, not an inventory.
    return [...gaps.value, ...weak.value, ...strong.value];
});

function add() {
    if (!terms.value.trim()) return;

    processing.value = true;

    router.post(
        '/admin/seo/keywords',
        { locale: locale.value, terms: terms.value, group: group.value || null },
        {
            preserveScroll: true,
            onSuccess: () => (terms.value = ''),
            onFinish: () => (processing.value = false),
        }
    );
}

function remove(row) {
    router.delete(`/admin/seo/keywords/${row.id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="t('settings.screen.keywords')">
        <Workspace>
            <Panel :title="t('settings.screen.keywords')">
                <p class="intro">{{ t('settings.keywords.intro') }}</p>
                <p class="honest">{{ t('settings.keywords.honest') }}</p>

                <!-- Entry: one box, any number of terms, any separator. -->
                <div class="add">
                    <div class="add__row">
                        <Field
                            v-model="locale"
                            :label="t('admin.language')"
                            type="select"
                            :options="locales.map((l) => ({ value: l, label: l }))"
                        />
                        <Field
                            v-model="group"
                            :label="t('settings.keywords.group')"
                            :hint="t('settings.keywords.group_hint')"
                            type="text"
                            :list="groups"
                        />
                    </div>

                    <Field
                        v-model="terms"
                        :label="t('settings.keywords.bulk')"
                        :hint="t('settings.keywords.bulk_hint')"
                        type="textarea"
                        :rows="4"
                        :dir="locale === 'en' ? 'ltr' : null"
                    />

                    <button class="btn btn--cta" type="button" :disabled="processing" @click="add">
                        {{ processing ? t('admin.saving') : t('settings.keywords.add') }}
                    </button>
                </div>

                <!-- Summary: three numbers that say what to do next. -->
                <div class="tally" role="group">
                    <button
                        class="tally__cell"
                        :class="{ 'is-active': filter === 'all' }"
                        type="button"
                        @click="filter = 'all'"
                    >
                        <span class="tally__n">{{ number(rows.length) }}</span>
                        <span class="tally__l">{{ t('settings.keywords.all') }}</span>
                    </button>
                    <button
                        class="tally__cell tally__cell--gap"
                        :class="{ 'is-active': filter === 'gaps' }"
                        type="button"
                        @click="filter = 'gaps'"
                    >
                        <span class="tally__n">{{ number(gaps.length) }}</span>
                        <span class="tally__l">{{ t('settings.keywords.gaps') }}</span>
                    </button>
                    <button
                        class="tally__cell tally__cell--weak"
                        :class="{ 'is-active': filter === 'weak' }"
                        type="button"
                        @click="filter = 'weak'"
                    >
                        <span class="tally__n">{{ number(weak.length) }}</span>
                        <span class="tally__l">{{ t('settings.keywords.weak') }}</span>
                    </button>
                    <button
                        class="tally__cell tally__cell--strong"
                        :class="{ 'is-active': filter === 'strong' }"
                        type="button"
                        @click="filter = 'strong'"
                    >
                        <span class="tally__n">{{ number(strong.length) }}</span>
                        <span class="tally__l">{{ t('settings.keywords.strong') }}</span>
                    </button>
                </div>

                <p v-if="!rows.length" class="empty">{{ t('settings.keywords.none') }}</p>

                <ul v-else class="terms">
                    <li
                        v-for="row in shown"
                        :key="row.id"
                        class="term"
                        :class="row.strong ? 'is-strong' : row.covered ? 'is-weak' : 'is-gap'"
                    >
                        <div class="term__head">
                            <span class="term__text">{{ row.term }}</span>
                            <span v-if="row.group" class="term__group">{{ row.group }}</span>

                            <span class="term__state">
                                {{ row.strong
                                    ? t('settings.keywords.state_strong')
                                    : row.covered
                                        ? t('settings.keywords.state_weak')
                                        : t('settings.keywords.state_gap') }}
                            </span>

                            <!-- The next question, one click away.
                                 This screen answers "does the site say this
                                 anywhere"; once it does, the useful question is
                                 whether the page that says it is built around
                                 it. The first matching page is carried across
                                 so the other screen opens on it already. -->
                            <Link
                                class="term__analyse"
                                :href="`/admin/seo/page-keywords?locale=${row.locale}${
                                    row.pages.length ? `&page_id=${row.pages[0].id}` : ''
                                }`"
                            >
                                {{ t('settings.keywords.analyse_page') }}
                            </Link>

                            <button class="term__del" type="button" @click="remove(row)">
                                {{ t('admin.delete') }}
                            </button>
                        </div>

                        <p v-if="!row.covered" class="term__advice">
                            {{ t('settings.keywords.gap_advice') }}
                        </p>

                        <ul v-else class="hits">
                            <li v-for="page in row.pages" :key="page.id" class="hit">
                                <Link class="link-weave" :href="`/admin/pages/${page.id}/edit`">
                                    {{ page.title ?? page.slug }}
                                </Link>
                                <span class="hit__where" :class="{ 'is-title': page.inTitle }">
                                    {{ page.inTitle
                                        ? t('settings.keywords.in_title')
                                        : t('settings.keywords.in_body') }}
                                </span>
                            </li>
                        </ul>
                    </li>
                </ul>
            </Panel>
    
        </Workspace>
    </AdminLayout>
</template>

<style scoped>
.intro {
    color: var(--muted);
    font-size: var(--fs-sm);
    max-inline-size: 75ch;
}

.honest {
    margin-block: var(--s-4) var(--s-6);
    padding: var(--s-3) var(--s-4);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--navy-900);
    font-size: var(--fs-sm);
    line-height: 1.8;
    max-inline-size: 80ch;
}

.add {
    padding: var(--s-5);
    border-radius: var(--r-md);
    background: var(--paper-alt);
    display: grid;
    gap: var(--s-4);
}

.add__row {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

.tally {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--s-3);
    margin-block: var(--s-6) var(--s-5);
}

.tally__cell {
    display: grid;
    gap: var(--s-1);
    padding: var(--s-4);
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    text-align: start;
}

.tally__cell.is-active {
    box-shadow: inset 0 0 0 2px var(--navy-900);
}

.tally__n {
    font-size: var(--fs-h2);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1.1;
}

.tally__l {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.tally__cell--gap .tally__n {
    color: var(--action-600);
}

.tally__cell--weak .tally__n {
    color: #9A6B00;
}

.tally__cell--strong .tally__n {
    color: #16643B;
}

.empty {
    color: var(--muted);
    font-size: var(--fs-sm);
}

.terms {
    display: grid;
    gap: var(--s-3);
}

.term {
    padding: var(--s-4);
    border-radius: var(--r-md);
    background: var(--paper);
    /* A coloured edge on the inline-start side, so it reads as a status
       marker in both reading directions. */
    box-shadow: inset 0 0 0 1px var(--hairline);
    border-inline-start: 3px solid var(--hairline);
}

.term.is-gap {
    border-inline-start-color: var(--action-600);
}

.term.is-weak {
    border-inline-start-color: var(--gold-400);
}

.term.is-strong {
    border-inline-start-color: #2E9E68;
}

.term__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
}

.term__text {
    font-weight: 700;
}

.term__group,
.term__state {
    font-size: var(--fs-xs);
    color: var(--muted);
    padding: 2px var(--s-2);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

/* This pair sits at the trailing edge of the row. The auto margin lives on
   the first of the two so they stay together rather than splitting the gap. */
.term__analyse {
    margin-inline-start: auto;
    font-size: var(--fs-xs);
    font-weight: 600;
    color: var(--action-600);
    min-block-size: 44px;
    padding-inline: var(--s-2);
    display: inline-flex;
    align-items: center;
    text-decoration: underline;
}

.term__del {
    font-size: var(--fs-xs);
    font-weight: 600;
    color: var(--action-600);
    min-block-size: 44px;
    padding-inline: var(--s-2);
}

.term__advice {
    margin-block-start: var(--s-2);
    font-size: var(--fs-sm);
    color: var(--action-600);
}

.hits {
    margin-block-start: var(--s-2);
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2) var(--s-4);
}

.hit {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    font-size: var(--fs-sm);
}

.hit__where {
    font-size: var(--fs-xs);
    color: var(--muted);
}

.hit__where.is-title {
    color: #16643B;
    font-weight: 600;
}

@media (min-width: 900px) {
    .add__row {
        grid-template-columns: 200px 1fr;
    }

    .tally {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
