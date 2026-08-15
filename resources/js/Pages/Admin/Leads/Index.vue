<script setup>
import { reactive, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
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
    /** The interest tags that actually appear on leads — sponsor, trainee… */
    interests: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    selected: { type: Object, default: null },
});

const { t } = useTranslation();
const { dateTime } = useFormat();
const page = usePage();

/**
 * An interest tag as a person should read it.
 *
 * The tag is a free string set from a section setting, so `t()` cannot be
 * called on it directly — a value with no translation would render the key
 * itself. Known tags get their Arabic label; anything the client invents later
 * shows as typed, which is the correct failure.
 */
function interestLabel(value) {
    return (page.props.translations ?? {})[`admin.interest_${value}`] ?? value;
}

/** One tap to reply, whichever way the visitor chose to be reached. */
function contactHref(lead) {
    return lead.type === 'phone'
        ? `tel:${String(lead.contact).replace(/\s/g, '')}`
        : `mailto:${lead.contact}`;
}

/**
 * The provider's name, or what "null" actually means.
 *
 * `null` is the stub driver that accepts a lead and sends it nowhere — the
 * setting a site runs on before a CRM is connected. Printed raw it read as
 * the word "null" beside every synced lead, which looks like a fault and is
 * really a configuration state worth naming.
 */
function providerLabel(provider) {
    return provider === 'null' || !provider
        ? t('admin.crm_provider_none')
        : provider;
}

/**
 * What actually happened to this lead, not what the column was told.
 *
 * The stub provider marks a lead "synced" after sending it nowhere, so a site
 * with no CRM connected showed a full column of green. Until a real provider
 * is configured that state is reported as what it is: nothing was sent.
 */
function crmState(lead) {
    return lead.crmProvider === 'null' ? 'not_connected' : lead.crmStatus;
}

/** Whether anything actually tagged this visit as coming from a campaign. */
function hasCampaignTags(lead) {
    return Object.values(lead.utm ?? {}).some(Boolean);
}

/**
 * Where the enquiry came from, in the order the answer is worth having.
 *
 * A campaign beats a source tag, a source tag beats a referring site, and a
 * visitor with none of those came directly — which is a fact, not a blank.
 * The column used to print "—" for all four cases at once.
 */
function originOf(lead) {
    if (lead.campaign) return lead.campaign;
    if (lead.source) return lead.source;

    if (lead.referrer) {
        try {
            const host = new URL(lead.referrer).hostname.replace(/^www\./, '');

            // A referrer on our own domain is the visitor moving around the
            // site, not a source — the page they came from is in the panel.
            if (host !== window.location.hostname) return host;
        } catch {
            return lead.referrer;
        }
    }

    return t('admin.lead_source_direct');
}

const filters = reactive({
    q: props.filters.q ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    campaign: props.filters.campaign ?? '',
    source: props.filters.source ?? '',
    status: props.filters.status ?? '',
    crm: props.filters.crm ?? '',
    interest: props.filters.interest ?? '',
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
                <!-- Only offered once a page has actually produced tagged
                     leads: an empty filter teaches an operator to distrust
                     the ones beside it. -->
                <Field
                    v-if="interests.length"
                    v-model="filters.interest"
                    :label="t('admin.lead_interest')"
                    type="select"
                    :options="interests.map((i) => ({ value: i, label: interestLabel(i) }))"
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
                            <th>{{ t('admin.lead_interest') }}</th>
                            <th>{{ t('admin.lead_status') }}</th>
                            <th>{{ t('admin.crm_state') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in leads.data" :key="lead.id">
                            <td class="nowrap">{{ dateTime(lead.createdAt) }}</td>
                            <!--
                                Both ways of reaching them, in the column that
                                claims to hold the contact. The phone lived in
                                `extra` and never left the database, so a
                                salesperson scanning this list saw an email and
                                assumed that was all there was.
                            -->
                            <td>
                                <Link :href="`/admin/leads/${lead.id}`" class="table__link latin">
                                    {{ lead.contact }}
                                </Link>
                                <a
                                    v-if="lead.phone"
                                    class="table__phone latin"
                                    :href="`tel:${lead.phone.replace(/\s/g, '')}`"
                                >
                                    {{ lead.phone }}
                                </a>
                                <span v-if="lead.organisation" class="table__org">{{ lead.organisation }}</span>
                            </td>
                            <!--
                                Clamped, with the whole thing one click away
                                on the lead's own page. `title` puts it in a
                                tooltip too, so scanning the column does not
                                cost a page load.
                            -->
                            <td class="table__msg" :title="lead.message ?? undefined">
                                {{ lead.message ?? '—' }}
                            </td>
                            <!-- Never a bare dash: a campaign if it was tagged,
                                 otherwise the site that sent them, otherwise
                                 the plain fact that they came directly. -->
                            <td class="table__src" :title="lead.referrer ?? undefined">
                                {{ originOf(lead) }}
                            </td>
                            <td class="nowrap">
                                {{ lead.interest ? interestLabel(lead.interest) : '—' }}
                            </td>
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
                                <span class="chip" :class="`chip--${crmState(lead)}`">
                                    {{ t(`admin.crm_${crmState(lead)}`) }}
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

                    <!-- The contact the visitor typed, and everything else the
                         form collected. `extra` holds the phone number and the
                         organisation: both were captured, stored and sent to
                         this screen, and until now neither was ever drawn —
                         the one fact a salesperson needs before calling. -->
                    <dt>{{ t('admin.lead_contact') }}</dt>
                    <dd class="ltr">
                        <a :href="contactHref(selected)" class="link-weave">{{ selected.contact }}</a>
                    </dd>

                    <template v-for="(value, key) in selected.extra ?? {}" :key="key">
                        <dt>{{ t(`admin.lead_extra_${key}`) }}</dt>
                        <dd :class="{ ltr: key === 'phone' }">
                            <!-- A phone is a link: one tap to call from a
                                 phone, one click to dial from a desktop app. -->
                            <a v-if="key === 'phone'" class="link-weave" :href="`tel:${String(value).replace(/\s/g, '')}`">
                                {{ value }}
                            </a>
                            <template v-else>{{ value }}</template>
                        </dd>
                    </template>

                    <dt>{{ t('admin.lead_message') }}</dt>
                    <dd>{{ selected.message ?? '—' }}</dd>

                    <dt>{{ t('admin.lead_status') }}</dt>
                    <dd>{{ t(`admin.status_${selected.status}`) }}</dd>

                    <dt>{{ t('admin.lead_campaign') }}</dt>
                    <dd>{{ selected.campaign ?? '—' }}</dd>

                    <dt>{{ t('admin.sectors') }}</dt>
                    <dd>{{ selected.sectorHint ?? '—' }}</dd>

                    <dt>{{ t('admin.lead_interest') }}</dt>
                    <dd>{{ selected.interest ? interestLabel(selected.interest) : '—' }}</dd>
                </dl>

                <h3 class="drawer__sub">{{ t('admin.attribution') }}</h3>
                <dl class="pairs">
                    <dt>{{ t('admin.lead_page_url') }}</dt>
                    <dd class="ltr">{{ selected.pageUrl ?? '—' }}</dd>
                    <dt>{{ t('admin.lead_referrer') }}</dt>
                    <dd class="ltr">{{ selected.referrer ?? '—' }}</dd>
                    <!--
                        One loop, not two.

                        These were two sibling `v-for`s — every label first,
                        then every value — which in this two-column grid put
                        `source` beside `medium` and left the values orphaned
                        in a block of dashes underneath. A definition list has
                        to alternate dt,dd,dt,dd to mean anything.

                        Rows only for the tags this visit actually carried. An
                        enquiry that arrived without a campaign printed nine
                        rows of `gclid —` in English, which reads as nine
                        missing facts rather than as one answered question; the
                        note underneath is that answer.
                    -->
                    <template v-for="(value, key) in selected.utm" :key="key">
                        <template v-if="value">
                            <dt>{{ t(`admin.utm_${key}`) }}</dt>
                            <dd class="ltr">{{ value }}</dd>
                        </template>
                    </template>
                </dl>

                <!-- Said plainly rather than left as a row of dashes: a visitor
                     who typed the address or followed a plain link carries no
                     campaign tags, and that is an answer, not a gap. -->
                <p v-if="!hasCampaignTags(selected)" class="drawer__note">
                    {{ t('admin.attribution_direct') }}
                </p>

                <h3 class="drawer__sub">{{ t('admin.crm_log') }}</h3>
                <p class="drawer__crm">
                    {{ t(`admin.crm_${crmState(selected)}`) }}
                    <span v-if="selected.crmProvider && selected.crmProvider !== 'null'" class="latin">· {{ providerLabel(selected.crmProvider) }}</span>
                </p>

                <button
                    v-if="can.updateStatus && crmState(selected) !== 'synced'"
                    class="btn btn--secondary"
                    type="button"
                    @click="resync(selected)"
                >
                    {{ t('admin.crm_resync') }}
                </button>

                <ul v-if="selected.syncLogs?.length" class="logs">
                    <li v-for="log in selected.syncLogs" :key="log.id" class="logs__row">
                        <span class="tabular">#{{ log.attempt }}</span>
                        <span class="latin">{{ providerLabel(log.provider) }}</span>
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

/*
 * Two lines, then an ellipsis.
 *
 * The width was capped and the text was not, so a 500-character message —
 * and people do paste their whole CV into a contact form — became a
 * fifteen-line row and pushed every other lead off the screen. The full text
 * is on the lead's own page, which the contact link opens.
 */
/* Under the email, quieter than it: the address is the link that opens the
   lead, the number is a shortcut to dial. */
.table__phone {
    display: block;
    direction: ltr;
    text-align: start;
    font-size: var(--fs-caption);
    color: var(--action-600);
}

.table__org {
    display: block;
    font-size: var(--fs-caption);
    color: var(--ink-600);
    overflow-wrap: anywhere;
}

.table__src {
    overflow-wrap: anywhere;
}

.drawer__note {
    margin-block-start: var(--s-2);
    font-size: var(--fs-caption);
    line-height: var(--lh-body);
    color: var(--ink-600);
}

.table__msg {
    max-inline-size: 24rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
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
