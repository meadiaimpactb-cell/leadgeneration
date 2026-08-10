<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * The measurement dashboard (§14.2, §19.13).
 *
 * Reports leads and response state only. There are no sales figures here, and
 * there are no target numbers — §21 defers those until after launch.
 */
const props = defineProps({
    stats: { type: Object, required: true },
    daily: { type: Array, default: () => [] },
    bySource: { type: Array, default: () => [] },
    byCampaign: { type: Array, default: () => [] },
    latest: { type: Array, default: () => [] },
    crmDriver: { type: String, default: null },
});

const { t } = useTranslation();
const { number, dayMonth } = useFormat();

const tiles = computed(() => [
    { label: t('admin.total_leads'), value: props.stats.total, tone: 'navy' },
    { label: t('admin.leads_30d'), value: props.stats.recent, change: props.stats.change, tone: 'navy' },
    { label: t('admin.qualified_leads'), value: props.stats.qualified, tone: 'navy' },
    { label: t('admin.unanswered'), value: props.stats.unanswered, tone: 'accent' },
    { label: t('admin.crm_failures'), value: props.stats.failedCrm, tone: props.stats.failedCrm > 0 ? 'danger' : 'navy' },
]);

// A simple bar chart in plain SVG — no charting library, so the dashboard
// costs nothing against the JS budget (§15.1).
const peak = computed(() => Math.max(1, ...props.daily.map((d) => d.count)));

const maxSource = computed(() => Math.max(1, ...props.bySource.map((s) => s.count)));
const maxCampaign = computed(() => Math.max(1, ...props.byCampaign.map((s) => s.count)));

// Date formatting lives in useFormat — Gregorian, Latin digits, both locales.
</script>

<template>
    <AdminLayout :title="t('admin.dashboard')">
        <p v-if="crmDriver === 'null'" class="warn" role="alert">
            {{ t('admin.crm_driver_null_warning') }}
        </p>

        <ul class="tiles">
            <li v-for="tile in tiles" :key="tile.label" class="tile" :class="`tile--${tile.tone}`">
                <p class="tile__label">{{ tile.label }}</p>
                <p class="tile__value tabular">{{ number(tile.value) }}</p>
                <p v-if="tile.change !== undefined && tile.change !== null" class="tile__change tabular">
                    {{ tile.change > 0 ? '+' : '' }}{{ number(tile.change) }}%
                </p>
            </li>
        </ul>

        <Panel :title="t('admin.leads_by_day')" :hint="t('admin.no_targets_note')">
            <div class="chart" role="img" :aria-label="t('admin.leads_by_day')">
                <svg :viewBox="`0 0 ${daily.length * 4} 60`" preserveAspectRatio="none" class="chart__svg">
                    <rect
                        v-for="(day, i) in daily"
                        :key="day.date"
                        :x="i * 4"
                        :y="60 - (day.count / peak) * 60"
                        width="3"
                        :height="Math.max((day.count / peak) * 60, day.count > 0 ? 2 : 0)"
                        fill="#002546"
                    />
                </svg>
                <div class="chart__axis">
                    <span>{{ daily.length ? dayMonth(daily[0].date) : '' }}</span>
                    <span>{{ daily.length ? dayMonth(daily[daily.length - 1].date) : '' }}</span>
                </div>
            </div>
        </Panel>

        <div class="split">
            <Panel :title="t('admin.leads_by_source')">
                <p v-if="!bySource.length" class="empty">{{ t('admin.no_records') }}</p>
                <ul v-else class="bars">
                    <li v-for="row in bySource" :key="row.label" class="bars__row">
                        <span class="bars__label">{{ row.label }}</span>
                        <span class="bars__track">
                            <span class="bars__fill" :style="{ inlineSize: `${(row.count / maxSource) * 100}%` }" />
                        </span>
                        <span class="bars__value tabular">{{ number(row.count) }}</span>
                    </li>
                </ul>
            </Panel>

            <Panel :title="t('admin.leads_by_campaign')">
                <p v-if="!byCampaign.length" class="empty">{{ t('admin.no_records') }}</p>
                <ul v-else class="bars">
                    <li v-for="row in byCampaign" :key="row.label" class="bars__row">
                        <span class="bars__label">{{ row.label }}</span>
                        <span class="bars__track">
                            <span class="bars__fill" :style="{ inlineSize: `${(row.count / maxCampaign) * 100}%` }" />
                        </span>
                        <span class="bars__value tabular">{{ number(row.count) }}</span>
                    </li>
                </ul>
            </Panel>
        </div>

        <Panel :title="t('admin.latest_leads')">
            <template #actions>
                <Link href="/admin/leads" class="btn btn--secondary">{{ t('admin.leads') }}</Link>
            </template>

            <p v-if="!latest.length" class="empty">{{ t('admin.no_records') }}</p>

            <ul v-else class="latest">
                <li v-for="lead in latest" :key="lead.id" class="latest__row">
                    <Link :href="`/admin/leads/${lead.id}`" class="latest__contact latin">
                        {{ lead.contact }}
                    </Link>
                    <span class="latest__msg">{{ lead.message ?? '—' }}</span>
                    <span class="latest__status">{{ t(`admin.status_${lead.status}`) }}</span>
                    <span class="latest__crm" :class="`is-${lead.crmStatus}`">
                        {{ t(`admin.crm_${lead.crmStatus}`) }}
                    </span>
                </li>
            </ul>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.warn {
    margin-block-end: var(--s-5);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--action-600);
    font-weight: 600;
}

.tiles {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: repeat(2, 1fr);
    margin-block-end: var(--s-5);
}

.tile {
    padding: var(--s-5);
    background: var(--paper);
    border-radius: var(--r-md);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.tile__label {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.tile__value {
    margin-block-start: var(--s-2);
    font-size: var(--fs-h1);
    font-weight: 600;
    color: var(--navy-900);
    line-height: 1;
}

.tile--accent .tile__value {
    color: var(--action-600);
}

.tile--danger .tile__value {
    color: var(--action-600);
}

.tile__change {
    margin-block-start: var(--s-1);
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.chart__svg {
    inline-size: 100%;
    block-size: 120px;
}

.chart__axis {
    display: flex;
    justify-content: space-between;
    margin-block-start: var(--s-2);
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.split {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-5);
}

.split :deep(.panel + .panel) {
    margin-block-start: 0;
}

.bars {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
}

.bars__row {
    display: grid;
    grid-template-columns: 8rem 1fr 3rem;
    gap: var(--s-3);
    align-items: center;
    font-size: var(--fs-sm);
}

.bars__label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bars__track {
    block-size: 8px;
    background: var(--navy-100);
    border-radius: var(--r-pill);
    overflow: hidden;
}

.bars__fill {
    display: block;
    block-size: 100%;
    background: var(--navy-900);
}

.bars__value {
    text-align: end;
    color: var(--text-muted);
}

.latest {
    display: flex;
    flex-direction: column;
}

.latest__row {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--s-1);
    padding-block: var(--s-3);
    border-block-end: 1px solid var(--hairline);
    font-size: var(--fs-sm);
}

.latest__contact {
    font-weight: 600;
    color: var(--link);
}

.latest__msg,
.latest__status {
    color: var(--text-muted);
}

.latest__crm.is-failed {
    color: var(--action-600);
    font-weight: 600;
}

.empty {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 640px) {
    .tiles {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1024px) {
    .tiles {
        grid-template-columns: repeat(5, 1fr);
    }

    .split {
        grid-template-columns: 1fr 1fr;
    }

    .latest__row {
        grid-template-columns: 14rem 1fr 8rem 8rem;
        align-items: center;
    }
}
</style>
