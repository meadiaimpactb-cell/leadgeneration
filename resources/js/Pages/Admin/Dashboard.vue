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
    /*
     * Whether this administrator may see enquiries at all.
     *
     * The controller decides and sends nothing it should not; this flag exists
     * so the screen does not draw empty lead panels to somebody who will never
     * have any. It is presentation only — the authorisation is enforced
     * server-side (§9.2), and hiding these blocks is not what protects them.
     */
    maySeeLeads: { type: Boolean, default: false },
});

const { t } = useTranslation();
const { number, dayMonth } = useFormat();

const tiles = computed(() => (props.maySeeLeads
    ? [
        { label: t('admin.total_leads'), value: props.stats.total, tone: 'navy' },
        { label: t('admin.leads_30d'), value: props.stats.recent, change: props.stats.change, tone: 'navy' },
        { label: t('admin.qualified_leads'), value: props.stats.qualified, tone: 'navy' },
        { label: t('admin.unanswered'), value: props.stats.unanswered, tone: 'accent' },
        { label: t('admin.crm_failures'), value: props.stats.failedCrm, tone: props.stats.failedCrm > 0 ? 'danger' : 'navy' },
    ]
    : []));

// A simple bar chart in plain SVG — no charting library, so the dashboard
// costs nothing against the JS budget (§15.1).
const peak = computed(() => Math.max(1, ...props.daily.map((d) => d.count)));

const maxSource = computed(() => Math.max(1, ...props.bySource.map((s) => s.count)));
const maxCampaign = computed(() => Math.max(1, ...props.byCampaign.map((s) => s.count)));

// Date formatting lives in useFormat — Gregorian, Latin digits, both locales.
</script>

<template>
    <AdminLayout :title="t('admin.dashboard')">
      <div class="dashscreen">
        <!--
            The CRM driver warning was removed at the client's request.

            What it said: the configured driver is the null one, so leads are
            saved to the database but pushed nowhere. That is still true and
            still worth knowing — the "لم تصل إلى CRM" tile above is now the
            only place it shows, and it is a number rather than a sentence.
        -->
        <ul class="tiles">
            <li v-for="tile in tiles" :key="tile.label" class="tile" :class="`tile--${tile.tone}`">
                <p class="tile__label">{{ tile.label }}</p>
                <p class="tile__value tabular">{{ number(tile.value) }}</p>
                <!--
                    The trend, as a shape rather than a sentence. Up is not
                    automatically good and down is not automatically bad on
                    every one of these tiles, so the chip states the direction
                    and leaves the judgement to the person reading it.
                -->
                <p
                    v-if="tile.change !== undefined && tile.change !== null"
                    class="tile__change tabular"
                    :class="tile.change > 0 ? 'is-up' : (tile.change < 0 ? 'is-down' : 'is-flat')"
                >
                    <span aria-hidden="true">{{ tile.change > 0 ? '↑' : (tile.change < 0 ? '↓' : '→') }}</span>
                    {{ tile.change > 0 ? '+' : '' }}{{ number(tile.change) }}%
                </p>
            </li>
        </ul>

        <Panel v-if="maySeeLeads" :title="t('admin.leads_by_day')" :hint="t('admin.no_targets_note')">
            <div class="chart" role="img" :aria-label="t('admin.leads_by_day')">
                <svg :viewBox="`0 0 ${daily.length * 4} 60`" preserveAspectRatio="none" class="chart__svg">
                    <rect
                        v-for="(day, i) in daily"
                        :key="day.date"
                        :x="i * 4"
                        :y="60 - (day.count / peak) * 60"
                        width="3"
                        :height="Math.max((day.count / peak) * 60, day.count > 0 ? 2 : 0)"
                        class="chart__bar"
                    />
                </svg>
                <div class="chart__axis">
                    <span>{{ daily.length ? dayMonth(daily[0].date) : '' }}</span>
                    <span>{{ daily.length ? dayMonth(daily[daily.length - 1].date) : '' }}</span>
                </div>
            </div>
        </Panel>

        <div class="split">
            <Panel v-if="maySeeLeads" :title="t('admin.leads_by_source')">
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

            <Panel v-if="maySeeLeads" :title="t('admin.leads_by_campaign')">
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

        <Panel v-if="maySeeLeads" :title="t('admin.latest_leads')">
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
      </div>
    </AdminLayout>
</template>

<style scoped>
/* ============================================================
   /admin — the measurement dashboard

   The panel's shared language (type scale, panels, buttons,
   chips, empty state) lives in resources/css/admin.css under
   `.shell`. What follows is only this screen's own: the tiles,
   the two chart forms, and the latest-leads list.

   The screen answers one question — is the site producing
   enquiries, and is anyone answering them — so it is built as a
   descending hierarchy: five figures, then the shape of the last
   thirty days, then where they came from, then the ones that
   arrived most recently. Nothing competes with the figures.

   Colour is a signal here, never decoration. Four of the five
   tiles are navy because four of them are just counts; the two
   that can indicate a problem take gold and orange, and only
   when the number is not zero.
   ============================================================ */

/* ---- Figures ---------------------------------------------- */

.tiles {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-block-end: var(--s-5);
    list-style: none;
    padding: 0;
}

@media (min-width: 900px) {
    /* Five across on a desk. They are read as one row of facts,
       and wrapping the fifth onto its own line makes it look
       like a different kind of thing. */
    .tiles {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

.tile {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: var(--s-2);
    padding: var(--s-5);
    border: 1px solid var(--hairline-soft);
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: 0 1px 2px rgba(0, 37, 70, 0.04);
    overflow: hidden;
}

/*
 * A band on the reading edge, carrying the tile's meaning.
 *
 * It is a rule rather than a tinted card because five filled
 * cards in a row is a colour wheel, not a dashboard: the eye
 * has nowhere to rest and no tile is more urgent than another.
 * A 3px edge says the same thing and spends almost nothing.
 */
.tile::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-start: 0;
    inline-size: 3px;
    background: var(--band, rgba(0, 37, 70, 0.22));
}

.tile__label {
    margin: 0;
    font-family: var(--font-body);
    font-size: var(--t-label);
    font-weight: 600;
    color: var(--text-muted);
}

/*
 * The figure. Latin face, tabular, and the one size on the screen
 * allowed outside the panel's four — a number that is the whole
 * point of a tile is information, not emphasis.
 */
.tile__value {
    margin: 0;
    font-family: var(--font-body-en);
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.02em;
    color: var(--navy-900);
    direction: ltr;
}

.tile__change {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin: 0;
    align-self: flex-start;
    padding: 4px var(--s-3);
    border-radius: var(--r-pill);
    font-family: var(--font-mono);
    font-size: var(--t-meta);
    font-weight: 500;
    direction: ltr;
}

.tile__change.is-up   { background: rgba(30, 122, 90, 0.12);  color: var(--success); }
.tile__change.is-down { background: rgba(215, 101, 59, 0.12); color: var(--action-600); }
.tile__change.is-flat { background: rgba(0, 37, 70, 0.07);    color: var(--muted); }

/* The band per tone. `accent` is «بانتظار الرد» and `danger` is
   «لم تصل إلى CRM» — both are questions for a person, so they
   get the two colours the identity reserves for attention. */
.tile--navy   { --band: rgba(0, 37, 70, 0.22); }
.tile--accent { --band: var(--gold-400); }
.tile--danger { --band: var(--orange-500); }

/* A zero on a problem tile is good news and should look calm. */
.tile--danger .tile__value { color: var(--action-600); }

/* ---- Thirty days ------------------------------------------ */

.chart {
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
}

.chart__svg {
    inline-size: 100%;
    block-size: 140px;
    /* A ground for the bars to stand on rather than float above. */
    border-block-end: 1px solid var(--hairline);
}

/* Colour belongs in the stylesheet, not in a `fill` attribute on
   the element — it was a literal hex in the markup, which is the
   one place nobody looks when the palette changes. */
.chart__bar {
    fill: var(--navy-900);
    opacity: 0.85;
}

.chart__axis {
    display: flex;
    justify-content: space-between;
    font-family: var(--font-mono);
    font-size: var(--t-meta);
    color: var(--muted);
    direction: ltr;
}

/* ---- Where they came from --------------------------------- */

.split {
    display: grid;
    gap: var(--gutter);
    grid-template-columns: 1fr;
    margin-block-start: var(--s-5);
}

@media (min-width: 900px) {
    .split {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.bars {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
    list-style: none;
    padding: 0;
    margin: 0;
}

/*
 * Label, bar, value — three columns, so the numbers align down
 * the edge and the bars all start from the same place. They were
 * a flex row where each part took the width it happened to need,
 * which put every bar at a different origin and made the lengths
 * impossible to compare, which is the only thing a bar chart is
 * for.
 */
.bars__row {
    display: grid;
    grid-template-columns: minmax(0, 9rem) minmax(0, 1fr) auto;
    gap: var(--s-3);
    align-items: center;
}

.bars__label {
    font-size: var(--t-label);
    color: var(--navy-900);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bars__track {
    display: block;
    block-size: 10px;
    border-radius: var(--r-pill);
    background: rgba(0, 37, 70, 0.07);
    overflow: hidden;
}

.bars__fill {
    display: block;
    block-size: 100%;
    border-radius: var(--r-pill);
    background: var(--navy-900);
    transition: inline-size var(--dur-el) var(--ease);
}

.bars__value {
    font-family: var(--font-mono);
    font-size: var(--t-meta);
    font-weight: 500;
    color: var(--navy-900);
    direction: ltr;
}

/* ---- Latest enquiries ------------------------------------- */

.latest {
    display: flex;
    flex-direction: column;
    list-style: none;
    padding: 0;
    margin: 0;
}

/*
 * A row of the same four facts every time, in the same four
 * places: who, what they said, where it stands, whether it left
 * the building. Aligned as a grid so the eye reads down a column
 * instead of re-finding each field on every line.
 */
.latest__row {
    display: grid;
    grid-template-columns: minmax(0, 14rem) minmax(0, 1fr) auto auto;
    gap: var(--s-4);
    align-items: center;
    padding-block: var(--s-4);
    border-block-start: 1px solid var(--hairline-soft);
}

.latest__row:first-child {
    border-block-start: 0;
    padding-block-start: 0;
}

.latest__contact {
    font-weight: 700;
    color: var(--navy-900);
    direction: ltr;
    text-align: start;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-decoration: underline;
    text-decoration-color: transparent;
    text-underline-offset: 3px;
    transition: text-decoration-color var(--dur-micro) var(--ease);
}

.latest__contact:hover,
.latest__contact:focus-visible {
    text-decoration-color: var(--orange-500);
}

.latest__msg {
    color: var(--text-muted);
    font-size: var(--t-label);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.latest__status {
    padding: 5px var(--s-3);
    border-radius: var(--r-pill);
    background: rgba(0, 37, 70, 0.07);
    color: var(--navy-900);
    font-size: var(--t-meta);
    font-weight: 700;
    white-space: nowrap;
}

.latest__crm {
    font-size: var(--t-meta);
    font-weight: 700;
    white-space: nowrap;
    color: var(--muted);
}

.latest__crm.is-synced  { color: var(--success); }
.latest__crm.is-pending { color: var(--action-600); }
.latest__crm.is-failed  { color: var(--action-600); }

/* ---- Small screens ---------------------------------------- */

@media (max-width: 767px) {
    .tile { padding: var(--s-4); }
    .tile__value { font-size: 1.625rem; }

    /* The bar's label and its value keep their line; the track
       takes the row underneath so neither is squeezed to nothing. */
    .bars__row {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .bars__track {
        grid-column: 1 / -1;
        order: 3;
    }

    /* Contact over message, state and CRM on one line beneath. */
    .latest__row {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .latest__msg {
        grid-column: 1 / -1;
        white-space: normal;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bars__fill,
    .latest__contact {
        transition: none;
    }
}
</style>
