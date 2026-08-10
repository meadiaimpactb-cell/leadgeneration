<script setup>
import { reactive, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * The leads list and its side panel (§11.4).
 *
 * Filters by date, campaign, source, status and CRM state; the export
 * respects the current filter set; a row opens the full detail with the UTM
 * set and the CRM sync log.
 */
const props = defineProps({
    leads: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    campaigns: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    selected: { type: Object, default: null },
});

const { t } = useTranslation();
const { dateTime } = useFormat();

const filters = reactive({
    q: props.filters.q ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    campaign: props.filters.campaign ?? '',
    source: props.filters.source ?? '',
    status: props.filters.status ?? '',
    crm: props.filters.crm ?? '',
});

let timer = null;

watch(
    filters,
    () => {
        // Debounced so typing in the search box does not fire a request per
        // keystroke.
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get('/admin/leads', clean(), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    },
    { deep: true }
);

function clean() {
    return Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null));
}

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ''));
}

function exportUrl() {
    const params = new URLSearchParams(clean()).toString();
    return `/admin/leads/export${params ? `?${params}` : ''}`;
}

function setStatus(lead, status) {
    router.patch(`/admin/leads/${lead.id}`, { status }, { preserveScroll: true, preserveState: true });
}

function resync(lead) {
    router.post(`/admin/leads/${lead.id}/resync`, {}, { preserveScroll: true, preserveState: true });
}

function closePanel() {
    router.get('/admin/leads', clean(), { preserveState: true, preserveScroll: true });
}

// Timestamps go through useFormat: Gregorian calendar, Latin digits.
</script>

<template>
    <AdminLayout :title="t('admin.leads')">
        <Panel :title="t('admin.filter')">
            <template #actions>
                <button class="btn btn--ghost" type="button" @click="reset">
                    {{ t('admin.reset') }}
                </button>
                <a v-if="can.export" class="btn btn--secondary" :href="exportUrl()">
                    {{ t('admin.export_csv') }}
                </a>
            </template>

            <div class="filters">
                <Field v-model="filters.q" :label="t('admin.search')" />
                <Field v-model="filters.from" :label="t('admin.lead_date')" type="date" />
                <Field v-model="filters.to" :label="t('admin.lead_date')" type="date" />
                <Field
                    v-model="filters.campaign"
                    :label="t('admin.lead_campaign')"
                    type="select"
                    :options="campaigns.map((c) => ({ value: c.id, label: c.slug }))"
                />
                <Field
                    v-model="filters.source"
                    :label="t('admin.lead_source')"
                    type="select"
                    :options="sources.map((s) => ({ value: s, label: s }))"
                />
                <Field
                    v-model="filters.status"
                    :label="t('admin.lead_status')"
                    type="select"
                    :options="statuses.map((s) => ({ value: s, label: t(`admin.status_${s}`) }))"
                />
                <Field
                    v-model="filters.crm"
                    :label="t('admin.crm_state')"
                    type="select"
                    :options="['pending', 'synced', 'failed'].map((s) => ({ value: s, label: t(`admin.crm_${s}`) }))"
                />
            </div>
        </Panel>

        <Panel :title="`${t('admin.leads')} (${leads.total})`">
            <p v-if="!leads.data.length" class="empty">{{ t('admin.no_records') }}</p>

            <div v-else class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t('admin.lead_date') }}</th>
                            <th>{{ t('admin.lead_contact') }}</th>
                            <th>{{ t('admin.lead_message') }}</th>
                            <th>{{ t('admin.lead_source') }}</th>
                            <th>{{ t('admin.lead_status') }}</th>
                            <th>{{ t('admin.crm_state') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in leads.data" :key="lead.id">
                            <td class="nowrap">{{ dateTime(lead.createdAt) }}</td>
                            <td>
                                <Link :href="`/admin/leads/${lead.id}`" class="table__link latin">
                                    {{ lead.contact }}
                                </Link>
                            </td>
                            <td class="table__msg">{{ lead.message ?? '—' }}</td>
                            <td>{{ lead.campaign ?? lead.source ?? '—' }}</td>
                            <td>
                                <select
                                    v-if="can.updateStatus"
                                    class="table__select"
                                    :value="lead.status"
                                    :aria-label="t('admin.lead_status')"
                                    @change="setStatus(lead, $event.target.value)"
                                >
                                    <option v-for="s in statuses" :key="s" :value="s">
                                        {{ t(`admin.status_${s}`) }}
                                    </option>
                                </select>
                                <span v-else>{{ t(`admin.status_${lead.status}`) }}</span>
                            </td>
                            <td>
                                <span class="chip" :class="`chip--${lead.crmStatus}`">
                                    {{ t(`admin.crm_${lead.crmStatus}`) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="leads.last_page > 1" class="pager" aria-label="pagination">
                <Link
                    v-for="link in leads.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="pager__link"
                    :class="{ 'is-current': link.active, 'is-disabled': !link.url }"
                    v-html="link.label"
                />
            </nav>
        </Panel>

        <!-- Side panel: full detail + UTM + CRM sync log (§11.4). -->
        <aside v-if="selected" class="drawer" role="dialog" aria-modal="false">
            <header class="drawer__head">
                <h2 class="drawer__title latin">{{ selected.contact }}</h2>
                <button class="drawer__close" type="button" :aria-label="t('common.close')" @click="closePanel">
                    ✕
                </button>
            </header>

            <div class="drawer__body">
                <dl class="pairs">
                    <dt>{{ t('admin.lead_date') }}</dt>
                    <dd>{{ dateTime(selected.createdAt) }}</dd>

                    <dt>{{ t('admin.lead_message') }}</dt>
                    <dd>{{ selected.message ?? '—' }}</dd>

                    <dt>{{ t('admin.lead_status') }}</dt>
                    <dd>{{ t(`admin.status_${selected.status}`) }}</dd>

                    <dt>{{ t('admin.lead_campaign') }}</dt>
                    <dd>{{ selected.campaign ?? '—' }}</dd>

                    <dt>{{ t('admin.sectors') }}</dt>
                    <dd>{{ selected.sectorHint ?? '—' }}</dd>
                </dl>

                <h3 class="drawer__sub">{{ t('admin.attribution') }}</h3>
                <dl class="pairs">
                    <dt>Page</dt>
                    <dd class="ltr">{{ selected.pageUrl ?? '—' }}</dd>
                    <dt>Referrer</dt>
                    <dd class="ltr">{{ selected.referrer ?? '—' }}</dd>
                    <dt v-for="(value, key) in selected.utm" :key="key">{{ key }}</dt>
                    <dd v-for="(value, key) in selected.utm" :key="`v-${key}`" class="ltr">
                        {{ value ?? '—' }}
                    </dd>
                </dl>

                <h3 class="drawer__sub">{{ t('admin.crm_log') }}</h3>
                <p class="drawer__crm">
                    {{ t(`admin.crm_${selected.crmStatus}`) }}
                    <span v-if="selected.crmProvider" class="latin">· {{ selected.crmProvider }}</span>
                </p>

                <button
                    v-if="can.updateStatus && selected.crmStatus !== 'synced'"
                    class="btn btn--secondary"
                    type="button"
                    @click="resync(selected)"
                >
                    {{ t('admin.crm_resync') }}
                </button>

                <ul v-if="selected.syncLogs?.length" class="logs">
                    <li v-for="log in selected.syncLogs" :key="log.id" class="logs__row">
                        <span class="tabular">#{{ log.attempt }}</span>
                        <span class="latin">{{ log.provider }}</span>
                        <span class="tabular">{{ log.httpStatus ?? '—' }}</span>
                        <span class="logs__error">{{ log.error ?? '' }}</span>
                    </li>
                </ul>
            </div>
        </aside>
    </AdminLayout>
</template>

<style scoped>
.filters {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

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
    vertical-align: top;
}

.table th {
    font-size: var(--fs-xs);
    color: var(--text-muted);
    white-space: nowrap;
}

.table__link {
    font-weight: 600;
    color: var(--link);
}

.table__msg {
    max-inline-size: 24rem;
}

.table__select {
    min-block-size: 44px;
    padding: var(--s-1) var(--s-2);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    font-size: var(--fs-sm);
}

.nowrap {
    white-space: nowrap;
}

.chip {
    display: inline-block;
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    font-size: var(--fs-xs);
    font-weight: 600;
    background: var(--navy-100);
    color: var(--navy-900);
}

.chip--synced {
    background: #e6f4ef;
    color: var(--success);
}

.chip--failed {
    background: var(--gold-100);
    color: var(--action-600);
}

.pager {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-1);
    margin-block-start: var(--s-5);
}

.pager__link {
    min-inline-size: 44px;
    min-block-size: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding-inline: var(--s-2);
    border-radius: var(--r-sm);
    font-size: var(--fs-sm);
    color: var(--navy-900);
}

.pager__link.is-current {
    background: var(--navy-900);
    color: #fff;
}

.pager__link.is-disabled {
    opacity: 0.4;
    pointer-events: none;
}

.drawer {
    position: fixed;
    inset-block: 0;
    inset-inline-end: 0;
    z-index: 70;
    inline-size: min(100%, 420px);
    background: var(--paper);
    box-shadow: 0 0 40px rgba(0, 37, 70, 0.18);
    display: flex;
    flex-direction: column;
}

.drawer__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
    padding: var(--s-4) var(--s-5);
    border-block-end: 1px solid var(--hairline);
}

.drawer__title {
    font-size: var(--fs-h3);
}

.drawer__close {
    inline-size: 44px;
    block-size: 44px;
}

.drawer__body {
    padding: var(--s-5);
    overflow-y: auto;
}

.drawer__sub {
    margin-block: var(--s-5) var(--s-3);
    font-size: var(--fs-sm);
    color: var(--text-muted);
}

.drawer__crm {
    margin-block-end: var(--s-3);
    font-weight: 600;
}

.pairs {
    display: grid;
    grid-template-columns: 9rem 1fr;
    gap: var(--s-2) var(--s-3);
    font-size: var(--fs-sm);
}

.pairs dt {
    color: var(--text-muted);
}

.pairs dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.ltr {
    direction: ltr;
    unicode-bidi: isolate;
    text-align: start;
}

.logs {
    margin-block-start: var(--s-4);
    display: flex;
    flex-direction: column;
    gap: var(--s-2);
    font-size: var(--fs-xs);
}

.logs__row {
    display: grid;
    grid-template-columns: 2rem 5rem 3rem 1fr;
    gap: var(--s-2);
    padding-block: var(--s-2);
    border-block-end: 1px solid var(--hairline);
}

.logs__error {
    color: var(--action-600);
    overflow-wrap: anywhere;
}

.empty {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 640px) {
    .filters {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .filters {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>
