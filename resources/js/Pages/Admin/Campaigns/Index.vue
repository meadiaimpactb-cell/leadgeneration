<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Campaigns list (§9.1). The lead count per campaign is the number the
 * campaigns team actually cares about, so it is in the table, not buried.
 */
defineProps({
    campaigns: { type: Array, default: () => [] },
});

const { t } = useTranslation();

async function destroy(campaign) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;
    router.delete(`/admin/campaigns/${campaign.id}`);
}
</script>

<template>
    <AdminLayout :title="t('admin.campaigns')">
        <Panel :title="t('admin.campaigns')">
            <template #actions>
                <Link href="/admin/campaigns/create" class="btn btn--cta">
                    {{ t('admin.campaign_wizard') }}
                </Link>
            </template>

            <p v-if="!campaigns.length" class="empty">{{ t('admin.no_records') }}</p>

            <div v-else class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t('admin.campaigns') }}</th>
                            <th>{{ t('admin.field_slug') }}</th>
                            <th>{{ t('admin.campaign_leads') }}</th>
                            <th>{{ t('admin.published') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="campaign in campaigns" :key="campaign.id">
                            <td>
                                <Link :href="`/admin/campaigns/${campaign.id}/edit`" class="table__link">
                                    {{ campaign.title ?? campaign.slug }}
                                </Link>
                            </td>
                            <td class="latin">{{ campaign.slug }}</td>
                            <td class="tabular">{{ campaign.leads }}</td>
                            <td>
                                <span class="chip" :class="campaign.isLive ? 'chip--ok' : ''">
                                    {{ campaign.isLive ? t('admin.published') : t('admin.draft') }}
                                </span>
                            </td>
                            <td class="actions">
                                <Link
                                    :href="`/admin/sections/campaign/${campaign.id}`"
                                    class="btn btn--ghost"
                                >
                                    {{ t('admin.sections') }}
                                </Link>
                                <a class="act btn btn--ghost" :href="campaign.previewUrl" target="_blank" rel="noopener">
                    <NavIcon name="eye" :size="18" :muted="false" />
                    <span>{{ t('admin.preview') }}</span>
                </a>
                                <button class="btn btn--ghost danger act act--icon" type="button" @click="destroy(campaign)"
                                    :title="t('admin.delete')"
                                    :aria-label="t('admin.delete')"><NavIcon name="trash" :size="18" :muted="false" /></button>
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
