<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Pages list (§9.1). Publish/unpublish and the preview link live here; the
 * page's body is built in the section builder.
 */
defineProps({
    pages: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
});

const { t } = useTranslation();

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

            <div v-else class="table-wrap">
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
                        <tr v-for="page in pages" :key="page.id" class="row">
                            <td class="cell-title">
                                <Link :href="`/admin/pages/${page.id}/edit`" class="table__link">
                                    {{ page.title ?? page.slug }}
                                </Link>
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
</style>
