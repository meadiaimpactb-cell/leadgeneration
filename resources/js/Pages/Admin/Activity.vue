<script setup>
import { reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * The audit trail (§9.1) — a reader, not an editor.
 *
 * There is no save button, no row action and no delete: the whole worth of
 * this screen is that the person whose actions it records cannot reach in and
 * change it. Everything here is a filter over what already happened.
 */
const props = defineProps({
    entries: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    events: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { dateTime } = useFormat();

const query = reactive({
    user: props.filters.user ?? '',
    event: props.filters.event ?? '',
    log: props.filters.log ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

watch(
    query,
    () => {
        router.get('/admin/activity', { ...query }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true }
);

/**
 * An event name the client can read.
 *
 * Falls back to the raw name rather than hiding the row: an event added by a
 * later phase must still appear, even before anyone writes a label for it.
 * A row missing from an audit trail is the one failure it cannot have.
 */
const eventLabel = (event) => {
    const key = `admin.activity_event_${event}`;
    const label = t(key);

    return label === key ? event : label;
};

const subjectLabel = (entry) => {
    if (!entry.subjectType) return '—';

    const key = `admin.activity_subject_${entry.subjectType.toLowerCase()}`;
    const label = t(key);

    return `${label === key ? entry.subjectType : label} #${entry.subjectId}`;
};

/** The changed field names, or the row's own summary. Never a value. */
const detail = (entry) => {
    const p = entry.properties ?? {};
    const parts = [];

    if (p.key) parts.push(p.key);
    if (p.email) parts.push(p.email);
    if (typeof p.rows === 'number') parts.push(t('admin.activity_rows', { count: p.rows }));
    if (Array.isArray(p.fields) && p.fields.length) parts.push(p.fields.join('، '));

    return parts.join(' · ');
};

/* An audit timestamp has to be readable next to the same event in the CRM and
 * in an exported CSV, so it goes through the one shared formatter — Gregorian
 * calendar, Latin digits — rather than whatever the browser's locale decides. */
const when = (iso) => dateTime(iso);
</script>

<template>
    <AdminLayout :title="t('admin.activity_log')">
        <p class="notice" role="note">{{ t('admin.activity_read_only') }}</p>

        <Panel :title="t('admin.activity_log')" :hint="t('admin.activity_hint')">
            <div class="filters">
                <Field v-model="query.user" :label="t('admin.activity_user')" type="select"
                       :options="[{ value: '', label: t('admin.activity_all') },
                                  ...users.map((u) => ({ value: String(u.id), label: u.name }))]" />
                <Field v-model="query.event" :label="t('admin.activity_event')" type="select"
                       :options="[{ value: '', label: t('admin.activity_all') },
                                  ...events.map((e) => ({ value: e, label: eventLabel(e) }))]" />
                <Field v-model="query.log" :label="t('admin.activity_area')" type="select"
                       :options="[{ value: '', label: t('admin.activity_all') },
                                  ...logs.map((l) => ({ value: l, label: l }))]" />
                <Field v-model="query.from" :label="t('admin.activity_from')" type="date" />
                <Field v-model="query.to" :label="t('admin.activity_to')" type="date" />
            </div>

            <p v-if="!entries.data.length" class="empty">{{ t('admin.activity_empty') }}</p>

            <div v-else class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ t('admin.activity_when') }}</th>
                            <th scope="col">{{ t('admin.activity_user') }}</th>
                            <th scope="col">{{ t('admin.activity_event') }}</th>
                            <th scope="col">{{ t('admin.activity_subject') }}</th>
                            <th scope="col">{{ t('admin.activity_detail') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in entries.data" :key="entry.id">
                            <td class="latin" dir="ltr">{{ when(entry.at) }}</td>
                            <!-- No causer means the system itself: a scheduled
                                 command, not a person who cannot be named. -->
                            <td>{{ entry.causer ?? t('admin.activity_system') }}</td>
                            <td><span class="chip">{{ eventLabel(entry.event) }}</span></td>
                            <td>{{ subjectLabel(entry) }}</td>
                            <td class="detail">{{ detail(entry) || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="entries.links && entries.links.length > 3" class="pager" :aria-label="t('admin.activity_pages')">
                <template v-for="link in entries.links" :key="link.label">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        :href="link.url || undefined"
                        class="pager__link"
                        :class="{ 'pager__link--on': link.active }"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.notice {
    margin-block-end: var(--s-5);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--navy-100);
    color: var(--navy-900);
    max-inline-size: 80ch;
}

.filters {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: 1fr;
    margin-block-end: var(--s-5);
}

.empty {
    color: var(--text-muted);
}

/* Wide content scrolls inside its own box; the page never scrolls sideways. */
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
    font-weight: 700;
    color: var(--navy-900);
    white-space: nowrap;
}

.detail {
    color: var(--text-muted);
    max-inline-size: 40ch;
}

.chip {
    display: inline-block;
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    background: var(--navy-100);
    color: var(--navy-900);
    font-size: var(--fs-xs);
    font-weight: 600;
    white-space: nowrap;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-block-start: var(--s-5);
}

.pager__link {
    padding: var(--s-2) var(--s-3);
    border-radius: var(--r-sm);
    min-inline-size: 44px;
    min-block-size: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--paper-alt);
    color: var(--navy-900);
}

.pager__link--on {
    background: var(--navy-900);
    color: var(--paper);
}

@media (min-width: 640px) {
    .filters {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .filters {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}
</style>
