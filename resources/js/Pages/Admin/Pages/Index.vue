<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
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

function destroy(page) {
    if (!confirm(t('admin.confirm_delete'))) return;
    router.delete(`/admin/pages/${page.id}`);
}
</script>

<template>
    <AdminLayout :title="t('admin.pages')">
        <Panel :title="t('admin.pages')">
            <template #actions>
                <Link href="/admin/pages/create" class="btn btn--cta">{{ t('admin.create') }}</Link>
            </template>

            <p v-if="!pages.length" class="empty">{{ t('admin.no_records') }}</p>

            <div v-else class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t('admin.pages') }}</th>
                            <th>slug</th>
                            <th>{{ t('admin.sections') }}</th>
                            <th>{{ t('admin.published') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="page in pages" :key="page.id">
                            <td>
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
                            <td class="latin">{{ page.slug }}</td>
                            <td>
                                <Link :href="`/admin/sections/page/${page.id}`" class="table__link">
                                    {{ page.sections }}
                                </Link>
                            </td>
                            <td>
                                <span class="chip" :class="page.status === 'published' ? 'chip--ok' : ''">
                                    {{ page.status === 'published' ? t('admin.published') : t('admin.draft') }}
                                </span>
                            </td>
                            <td class="actions">
                                <button class="btn btn--ghost" type="button" @click="togglePublish(page)">
                                    {{ page.status === 'published' ? t('admin.unpublish') : t('admin.publish') }}
                                </button>
                                <a class="btn btn--ghost" :href="page.previewUrl" target="_blank" rel="noopener">
                                    {{ t('admin.preview') }}
                                </a>
                                <button class="btn btn--ghost danger" type="button" @click="destroy(page)">
                                    {{ t('admin.delete') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.table-wrap {
    overflow-x: auto;
}

.table {
    inline-size: 100%;
    border-collapse: collapse;
    font-size: var(--fs-sm);
}

.table th,
.table td {
    padding: var(--s-3);
    text-align: start;
    border-block-end: 1px solid var(--hairline);
    vertical-align: middle;
}

.table th {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.table__link {
    font-weight: 600;
    color: var(--link);
}

.chip {
    display: inline-block;
    margin-inline-start: var(--s-2);
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    font-size: var(--fs-xs);
    font-weight: 600;
    background: var(--navy-100);
    color: var(--navy-900);
}

.chip--ok {
    background: #e6f4ef;
    color: var(--success);
}

.chip--warn {
    background: var(--gold-100);
    color: var(--action-600);
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-1);
}

.actions .btn {
    min-block-size: 44px;
    padding-inline: var(--s-3);
    font-size: var(--fs-xs);
}

.danger {
    color: var(--action-600);
}

.empty {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}
</style>
