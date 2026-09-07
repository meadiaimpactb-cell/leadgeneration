<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { confirmDialog } from '@/admin/confirm';
import { computed, ref } from 'vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Pages list (§9.1). Publish/unpublish and the preview link live here; the
 * page's body is built in the section builder.
 */
const props = defineProps({
    pages: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
});

const { t } = useTranslation();

/**
 * The pages an editor works on, and the ones that no longer answer.
 *
 * Since the landing-page decision the site is one page with twenty-seven
 * sections; the other eleven are retired and redirect. Listing all thirteen
 * as equals made the screen read as a site with thirteen pages — the editor
 * had to know which was which from memory, which is precisely what a panel
 * exists to stop.
 */
const live = computed(() => props.pages.filter((page) => !page.retired));
const retired = computed(() => props.pages.filter((page) => page.retired));

/** Retired rows stay collapsed: they are a record, not a workspace. */
const showRetired = ref(false);

/** What the table draws — the live pages, plus the retired ones when asked. */
const rows = computed(() => (showRetired.value ? [...live.value, ...retired.value] : live.value));

function togglePublish(page) {
    router.post(
        `/admin/pages/${page.id}/publish`,
        { published: page.status !== 'published' },
        { preserveScroll: true }
    );
}

async function destroy(page) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;
    router.delete(`/admin/pages/${page.id}`);
}
</script>

<template>
    <AdminLayout :title="t('admin.pages')">
      <div class="pagescreen">
        <Panel :title="t('admin.pages')">
            <template #actions>
                <Link href="/admin/pages/create" class="btn btn--cta act">
                    <NavIcon name="plus" :size="18" :muted="false" />
                    <span>{{ t('admin.create') }}</span>
                </Link>
            </template>

            <p v-if="!pages.length" class="empty">{{ t('admin.no_records') }}</p>

            <!--
                Wrapped in a <template>, and this is not a formatting choice.
                The disclosure below used to sit between the `v-if` above and
                the table's `v-else`, which silently re-paired that `v-else`
                with the BUTTON's condition — so the table rendered only when
                there were no retired pages, and the editor opened this screen
                to find every page gone. `v-if`/`v-else` must stay adjacent.
            -->
            <template v-else>
                <!--
                    The retired pages, behind a disclosure and closed by
                    default. They still exist and can still be opened — but
                    they answer 301 now, so they belong in a drawer marked as
                    such rather than interleaved with the page the editor came
                    here to work on.
                -->
                <button
                    v-if="retired.length"
                    type="button"
                    class="retired-toggle"
                    :aria-expanded="showRetired"
                    @click="showRetired = !showRetired"
                >
                    {{ showRetired ? t('admin.hide_retired_pages') : t('admin.show_retired_pages') }}
                    <span class="retired-toggle__count">{{ retired.length }}</span>
                </button>

            <div class="table-wrap">
                <table class="table">
                    <!--
                        Each column is given the width its content actually
                        needs, rather than letting the browser share the row
                        out evenly: the title is the thing being scanned and
                        takes what is left, while the slug, the count, the
                        state and the buttons are all fixed-size facts.
                    -->
                    <colgroup>
                        <col class="col-title" />
                        <col class="col-slug" />
                        <col class="col-sections" />
                        <col class="col-state" />
                        <col class="col-acts" />
                    </colgroup>

                    <thead>
                        <tr>
                            <th>{{ t('admin.pages') }}</th>
                            <th>{{ t('admin.field_slug') }}</th>
                            <th>{{ t('admin.sections') }}</th>
                            <th>{{ t('admin.published') }}</th>
                            <!-- Named, not blank: a column of buttons with no
                                 heading reads as something left unfinished. -->
                            <th>{{ t('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="page in rows" :key="page.id" class="row" :class="{ 'row--site': page.isSite }">
                            <td class="cell-title">
                                <Link :href="`/admin/pages/${page.id}/edit`" class="table__link">
                                    {{ page.title ?? page.slug }}
                                </Link>

                                <!-- The one page the site actually serves. -->
                                <span v-if="page.isSite" class="chip chip--site">
                                    {{ t('admin.the_site_page') }}
                                </span>

                                <!-- And where a retired one sends its visitors,
                                     so nobody has to guess whether it still
                                     does anything. -->
                                <span v-if="page.retired && page.redirectsTo" class="chip chip--retired">
                                    {{ t('admin.redirects_to') }} <code>{{ page.redirectsTo }}</code>
                                </span>
                                <!-- The "translation missing" indicator (§9.1). -->
                                <span
                                    v-for="loc in locales.filter((l) => !page.locales.includes(l))"
                                    :key="loc"
                                    class="chip chip--warn"
                                >
                                    {{ t('admin.translation_missing') }}: {{ loc }}
                                </span>
                            </td>
                            <!-- A slug is a URL fragment: Latin, monospaced,
                                 and never mirrored by an RTL document. -->
                            <td class="cell-slug"><code class="slug">{{ page.slug }}</code></td>
                            <td class="cell-sections">
                                <!--
                                    Was the section count as a bare text link.
                                    A number in a table cell does not read as a
                                    way in, so the one control an editor uses
                                    most was the one nobody found. Now it says
                                    what it opens, and carries the count.
                                -->
                                <Link
                                    :href="`/admin/sections/page/${page.id}`"
                                    class="btn btn--secondary act act--sections"
                                >
                                    <NavIcon name="layers" :size="18" :muted="false" />
                                    <span>{{ t('admin.sections') }}</span>
                                    <span class="act__count">{{ page.sections }}</span>
                                </Link>
                            </td>
                            <td class="cell-state">
                                <span
                                    class="chip"
                                    :class="page.status === 'published' ? 'chip--live' : 'chip--draft'"
                                >
                                    {{ page.status === 'published' ? t('admin.published') : t('admin.draft') }}
                                </span>
                            </td>
                            <!--
                                Icon-only from here down. These three controls
                                repeat on every row, and three Arabic words ×
                                eleven pages is a wall of text to read past
                                before finding the one row you came for. Each
                                keeps its word in `title` and `aria-label`, so
                                hover and screen readers lose nothing.
                            -->
                            <td class="cell-acts">
                                <!--
                                    Edit is here as well as on the title.
                                    The title link is the fast path for anyone
                                    who already knows it is a link; this column
                                    is for anyone reading the row for the first
                                    time and asking what can be done to it —
                                    which is the question the heading now names.
                                -->
                                <Link
                                    class="btn btn--ghost act act--icon"
                                    :href="`/admin/pages/${page.id}/edit`"
                                    :title="t('admin.edit')"
                                    :aria-label="t('admin.edit')"
                                >
                                    <NavIcon name="edit" :size="18" :muted="false" />
                                </Link>
                                <button
                                    class="btn btn--ghost act act--icon"
                                    type="button"
                                    :title="page.status === 'published' ? t('admin.unpublish') : t('admin.publish')"
                                    :aria-label="page.status === 'published' ? t('admin.unpublish') : t('admin.publish')"
                                    @click="togglePublish(page)"
                                >
                                    <NavIcon :name="page.status === 'published' ? 'unpublish' : 'publish'" :size="18" :muted="false" />
                                </button>
                                <a
                                    class="btn btn--ghost act act--icon"
                                    :href="page.previewUrl"
                                    target="_blank"
                                    rel="noopener"
                                    :title="t('admin.preview')"
                                    :aria-label="t('admin.preview')"
                                >
                                    <NavIcon name="eye" :size="18" :muted="false" />
                                </a>
                                <button
                                    class="btn btn--ghost danger act act--icon"
                                    type="button"
                                    :title="t('admin.delete')"
                                    :aria-label="t('admin.delete')"
                                    @click="destroy(page)"
                                >
                                    <NavIcon name="trash" :size="18" :muted="false" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </template>
        </Panel>
      </div>
    </AdminLayout>
</template>

<style scoped>
/* ============================================================
   /admin/pages — only what is this screen's own.

   The panel's shared language — type scale, controls, table,
   chips, buttons, pager — now lives in resources/css/admin.css
   under `.shell`, so it is written once for all twenty-six
   screens instead of copied into each. What stays here is the
   column geometry, which is particular to this table.
   ============================================================ */

/*
 * Column widths, set on <colgroup> rather than left to the
 * browser to share out evenly.
 *
 * Four of the five columns hold a fact of known size — a slug, a
 * count, a state, four buttons. Only the title varies, so only
 * the title gets what is left. Sharing the row equally gave the
 * buttons as much room as the page name and squeezed the one
 * column anybody actually reads.
 */
.col-title    { inline-size: auto; }
.col-slug     { inline-size: 22%; }
.col-sections { inline-size: 1%; }
.col-state    { inline-size: 1%; }
.col-acts     { inline-size: 1%; }

.table { min-inline-size: 720px; }

.cell-title { min-inline-size: 220px; }

.cell-sections,
.cell-state {
    white-space: nowrap;
}

/* One rhythm for the four buttons, and they stay on one line. */
.cell-acts {
    display: flex;
    gap: 4px;
    align-items: center;
    white-space: nowrap;
}

/* The sections button carries its count inside itself. */
.act--sections {
    min-block-size: 40px;
    padding-inline: var(--s-3);
    font-size: var(--t-meta);
    white-space: nowrap;
}

/* A missing translation IS a problem — the page is invisible to
   half the site's visitors — so it keeps the accent chip and a
   little air from the title beside it. */
.chip--warn {
    margin-inline-start: var(--s-2);
}

/* The site page: named, and lifted off the rows around it. */
.chip--site {
    background: var(--navy-900);
    color: #fff;
}

.row--site {
    background: rgba(0, 37, 70, 0.03);
}

/* A retired page is not an error and not a draft — it answers, it just
   answers 301. Muted, with the destination shown rather than described. */
.chip--retired {
    background: var(--sand);
    color: var(--text-muted);
}

.chip--retired code {
    font-family: var(--font-mono);
    direction: ltr;
    unicode-bidi: isolate;
}

.retired-toggle {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    margin-block-end: var(--s-4);
    padding: var(--s-2) var(--s-3);
    background: none;
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    color: var(--text-muted);
    font: inherit;
    font-size: var(--fs-sm);
    cursor: pointer;
}

.retired-toggle:hover {
    color: var(--text);
}

.retired-toggle__count {
    padding-inline: var(--s-2);
    border-radius: var(--r-pill);
    background: var(--sand);
    font-family: var(--font-mono);
    font-size: var(--fs-xs);
}
</style>
