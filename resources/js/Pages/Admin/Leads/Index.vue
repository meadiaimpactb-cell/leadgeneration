<script setup>
import { computed, reactive, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
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
    /** How many leads are put away, so the link to them can say so. */
    archivedCount: { type: Number, default: 0 },
    viewingArchived: { type: Boolean, default: false },
});

const { t } = useTranslation();
const { dateTime, date } = useFormat();

/**
 * The timestamp, split over two lines for the table.
 *
 * Both go through `useFormat`, and neither formats a date itself — the
 * calendar and the numerals are decided in one module for the whole panel, and
 * `NumeralFormattingTest` fails the build if a component decides them itself.
 * (That guard greps the whole file, comments included, so this note names no
 * browser API directly.)
 *
 * `dateTime` is untouched and still used by the side panel, where one line is
 * right because there is room for it.
 */
function dateOnly(iso) {
    return date(iso);
}

function timeOnly(iso) {
    if (! iso) return '';

    return date(iso, { year: undefined, month: undefined, day: undefined, hour: '2-digit', minute: '2-digit', hour12: false });
}
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

/**
 * How many filters are narrowing the list right now.
 *
 * Purely a readout of `clean()`, which already existed and already decides
 * what counts as "set". It is what lets the summary strip say why the total
 * is smaller than expected, and what disables «إعادة الضبط» when there is
 * nothing to reset — a live button that does nothing is a small lie.
 */
const activeFilterCount = computed(() => Object.keys(clean()).length);

function reset() {
    Object.keys(filters).forEach((k) => (filters[k] = ''));
}

function exportUrl() {
    const params = new URLSearchParams(clean()).toString();
    return `/admin/leads/export${params ? `?${params}` : ''}`;
}

/**
 * A link back to this list, keeping the filters, optionally toggling into the
 * archive.
 *
 * Built here and not in the template, and that is the whole point of it: a
 * template expression is compiled against the component's render context, so
 * `new URLSearchParams(…)` written inline resolves to `_ctx.URLSearchParams`
 * — undefined — and throws `is not a constructor` while rendering. It took the
 * whole screen down with it, and only on the accounts that had something
 * archived, because that is the only case where the link is drawn at all.
 *
 * `exportUrl()` above had it right from the start; this simply follows it.
 */
function listUrl(extra = {}) {
    const params = new URLSearchParams({ ...clean(), ...extra }).toString();

    return `/admin/leads${params ? `?${params}` : ''}`;
}

/**
 * A long field, cut to a readable length with a visible ellipsis.
 *
 * Done here rather than with `-webkit-line-clamp` alone: the clamp depends on
 * how many lines happen to fit at the current column width, so the same
 * message showed two lines on one screen and none on another, and the cut was
 * silent — nothing told the reader there was more. A character count cuts the
 * same way everywhere and the «…» says so out loud.
 *
 * The full text stays in `title`, and the row opens the record.
 */
function excerpt(value, max = 95) {
    if (! value) return '—';

    const text = String(value).trim();

    return text.length > max ? `${text.slice(0, max).trimEnd()}…` : text;
}

/**
 * Open a record by clicking anywhere on its row.
 *
 * The contact cell keeps its real link — that is the keyboard path and the
 * one a screen reader announces, and turning the row into the only way in
 * would have removed it. This is an additional affordance for the mouse, so
 * it steps aside whenever the click landed on something that already does
 * something: the status select, the archive button, the phone link.
 */
function openLead(lead, event) {
    if (event.target.closest('a, button, select, input, label')) {
        return;
    }

    router.get(`/admin/leads/${lead.id}`, clean(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function setStatus(lead, status) {
    router.patch(`/admin/leads/${lead.id}`, { status }, { preserveScroll: true, preserveState: true });
}

function resync(lead) {
    router.post(`/admin/leads/${lead.id}/resync`, {}, { preserveScroll: true, preserveState: true });
}

/**
 * Archive a lead, or bring it back — the same control both ways.
 *
 * No confirmation dialogue, deliberately: nothing is destroyed, the row is
 * one click from returning, and the toast says so. A confirm box in front of
 * a reversible act trains people to dismiss confirm boxes.
 *
 * `preserveState: false` so the list actually re-queries — the row has just
 * left the filter it was fetched under, and keeping the old state would
 * leave it sitting there looking unchanged.
 */
function toggleArchive(lead) {
    router.post(
        `/admin/leads/${lead.id}/archive`,
        {},
        { preserveScroll: true, preserveState: false },
    );
}

function closePanel() {
    router.get('/admin/leads', clean(), { preserveState: true, preserveScroll: true });
}

// Timestamps go through useFormat: Gregorian calendar, Latin digits.
</script>

<template>
    <AdminLayout :title="t('admin.leads')">
      <div class="leadscreen">
        <!--
            The three facts worth knowing before reading a single row: how many
            arrived under the current filter, how many are put away, and
            whether what you are looking at is the whole set or a slice of it.

            Every number here is already on the page — none is fetched, derived
            or estimated. The strip exists because they were scattered across a
            panel title, a button and a mental note.
        -->
        <header class="summary">
            <div class="summary__figure">
                <span class="summary__value tabular">{{ leads.total }}</span>
                <span class="summary__label">{{ viewingArchived ? t('admin.lead_archive_view') : t('admin.leads') }}</span>
            </div>

            <div v-if="activeFilterCount" class="summary__note">
                <NavIcon name="advanced" :size="16" :muted="false" />
                <span>{{ t('admin.filter') }} · {{ activeFilterCount }}</span>
                <button class="summary__clear" type="button" @click="reset">
                    {{ t('admin.reset') }}
                </button>
            </div>

            <div class="summary__acts">
                <a v-if="can.export" class="btn btn--secondary act" :href="exportUrl()">
                    <NavIcon name="swap" :size="18" :muted="false" />
                    <span>{{ t('admin.export_csv') }}</span>
                </a>

                <Link v-if="viewingArchived" class="btn btn--ghost act" :href="listUrl()">
                    <NavIcon name="back" :size="18" :muted="false" />
                    <span>{{ t('admin.lead_archive_back') }}</span>
                </Link>
                <Link
                    v-else-if="archivedCount > 0"
                    class="btn btn--ghost act"
                    :href="listUrl({ archived: 1 })"
                >
                    <NavIcon name="trash" :size="18" :muted="false" />
                    <span>{{ t('admin.lead_archive_view') }}</span>
                    <span class="act__count">{{ archivedCount }}</span>
                </Link>
            </div>
        </header>

        <Panel :title="t('admin.filter')">
            <template #actions>
                <button
                    class="btn btn--ghost act"
                    type="button"
                    :disabled="!activeFilterCount"
                    @click="reset"
                >
                    <NavIcon name="close" :size="18" :muted="false" />
                    <span>{{ t('admin.reset') }}</span>
                </button>
            </template>

            <!--
                What "archive" means here, said once at the top of the archive
                rather than implied. The word carries a promise of deletion in
                most systems, and this one deletes nothing.
            -->
            <p v-if="viewingArchived" class="archnote">
                <NavIcon name="shield" :size="18" :muted="false" />
                <span>{{ t('admin.lead_archive_note') }}</span>
            </p>

            <!--
                Search spans the row and the date pair sits together, because
                that is how they are used: you either hunt for one name or you
                bracket a period. Eight equal boxes made both look like the
                same kind of decision.
            -->
            <div class="filters">
                <!--
                    The search box carries a glyph inside it.

                    It is the one field on the screen that is used without
                    being read first — you arrive knowing you want to find
                    somebody. A mark inside the box says "search" faster than
                    the label above it does, and the label stays for anyone
                    who needs it.
                -->
                <div class="filters__search searchbox">
                    <NavIcon name="seo" :size="18" :muted="false" class="searchbox__glyph" />
                    <Field v-model="filters.q" :label="t('admin.search')" />
                </div>
                <Field v-model="filters.from" :label="t('admin.lead_date')" type="date" class="filters__from" />
                <Field v-model="filters.to" :label="t('admin.lead_date')" type="date" class="filters__to" />
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
                            <!--
                                Source and interest are not columns any more.

                                Both are answered on one lead at a time, not by
                                scanning a list — you look them up once you have
                                decided which enquiry you are dealing with. As
                                columns they cost two thirds of the row's width
                                and pushed the message, which IS scanned, into a
                                narrow strip. They live in the side panel now,
                                one click away, and both are still filters.
                            -->
                            <th>{{ t('admin.lead_message') }}</th>
                            <th>{{ t('admin.lead_status') }}</th>
                            <th>{{ t('admin.crm_state') }}</th>
                            <th v-if="can.archive">
                                <span class="visually-hidden">{{ t('admin.lead_archive') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="lead in leads.data"
                            :key="lead.id"
                            class="row"
                            :class="{ 'is-archived': lead.archived }"
                            @click="openLead(lead, $event)"
                        >
                            <!--
                                Date over time, not date-space-time. Scanning a
                                column for "which day" should not mean reading
                                past a clock on every row.
                            -->
                            <td class="cell-when">
                                <span class="when__date tabular">{{ dateOnly(lead.createdAt) }}</span>
                                <span class="when__time tabular">{{ timeOnly(lead.createdAt) }}</span>
                            </td>
                            <!--
                                Both ways of reaching them, in the column that
                                claims to hold the contact. The phone lived in
                                `extra` and never left the database, so a
                                salesperson scanning this list saw an email and
                                assumed that was all there was.
                            -->
                            <td class="cell-who">
                                <Link :href="`/admin/leads/${lead.id}`" class="table__link latin">
                                    {{ lead.contact }}
                                </Link>
                                <a
                                    v-if="lead.phone"
                                    class="table__phone latin"
                                    :href="`tel:${lead.phone.replace(/\s/g, '')}`"
                                >
                                    <NavIcon name="contact" :size="14" :muted="false" />
                                    <span>{{ lead.phone }}</span>
                                </a>
                                <span v-if="lead.organisation" class="table__org">{{ lead.organisation }}</span>
                            </td>
                            <!--
                                Clamped, with the whole thing one click away
                                on the lead's own page. `title` puts it in a
                                tooltip too, so scanning the column does not
                                cost a page load.
                            -->
                            <td class="cell-msg" :title="lead.message ?? undefined">
                                {{ excerpt(lead.message) }}
                            </td>
                            <!--
                                Still the same <select> and the same change
                                handler — it only stopped looking like a form
                                control dropped into a table. The status colour
                                rides on the wrapper so the cell reads as a
                                badge while staying a real, keyboard-operable
                                select underneath.
                            -->
                            <td class="cell-status">
                                <span
                                    v-if="can.updateStatus"
                                    class="statuspick"
                                    :class="`statuspick--${lead.status}`"
                                >
                                    <select
                                        class="table__select"
                                        :value="lead.status"
                                        :aria-label="t('admin.lead_status')"
                                        @change="setStatus(lead, $event.target.value)"
                                    >
                                        <option v-for="s in statuses" :key="s" :value="s">
                                            {{ t(`admin.status_${s}`) }}
                                        </option>
                                    </select>
                                    <NavIcon name="unpublish" :size="14" :muted="false" class="statuspick__caret" />
                                </span>
                                <span v-else class="chip" :class="`chip--st-${lead.status}`">
                                    {{ t(`admin.status_${lead.status}`) }}
                                </span>
                            </td>
                            <td>
                                <span class="chip" :class="`chip--${crmState(lead)}`">
                                    {{ t(`admin.crm_${crmState(lead)}`) }}
                                </span>
                            </td>
                            <!--
                                Archive, or put back — the same control either
                                way, because the act is its own undo. Icon
                                only: it repeats on every row of a table that
                                is already dense with words.
                            -->
                            <td v-if="can.archive" class="rowact">
                                <button
                                    class="btn btn--ghost act act--icon"
                                    :class="{ danger: !lead.archived }"
                                    type="button"
                                    :title="lead.archived ? t('admin.lead_restore') : t('admin.lead_archive')"
                                    :aria-label="lead.archived ? t('admin.lead_restore') : t('admin.lead_archive')"
                                    @click="toggleArchive(lead)"
                                >
                                    <NavIcon :name="lead.archived ? 'swap' : 'trash'" :size="18" :muted="false" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="leads.last_page > 1" class="pager" :aria-label="t('common.pagination')">
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

                    <!--
                        The resolved origin, now that the column is gone.

                        It was only ever drawn in the table, so dropping that
                        cell would have removed the answer from the product
                        rather than moving it. `originOf` is the same function
                        the column used: a campaign if it was tagged, else the
                        source, else the referring host, else the plain fact
                        that they arrived directly.
                    -->
                    <dt>{{ t('admin.lead_source') }}</dt>
                    <dd>{{ originOf(selected) }}</dd>

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
                    class="act btn btn--secondary"
                    type="button"
                    @click="resync(selected)"
                >
                    <NavIcon name="swap" :size="18" :muted="false" />
                    <span>{{ t('admin.crm_resync') }}</span>
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
      </div>
    </AdminLayout>
</template>

<style scoped>
/* ============================================================
   /admin/leads — only what is this screen's own.

   An operator opens this to answer one of three questions: has
   anything new arrived, who do I call next, and did it reach
   the CRM. Everything below is arranged so each is answered by
   scanning rather than by reading.

   The panel's shared language — the type scale, the controls,
   the table, chips, buttons, the pager, the empty state — lives
   in resources/css/admin.css under `.shell`, written once for
   all twenty-six screens. This screen used to carry its own copy
   of the lot: it agreed with the shared file value for value, so
   nothing looked wrong, but two files were being kept in step by
   hand and only one of them was ever going to get the next
   change. Follow Pages/Index.vue — the same reduction, already
   done there.

   What is genuinely this screen's own, and stays here:
     · the summary strip
     · the filter grid and its search box
     · the table's column geometry and sticky heading
     · the status picker
     · the CRM and status chip colours
     · the detail drawer

   ---- Colour ----

   Four identity colours and nothing else: navy #002546,
   lavender #8685D8, burnt orange #D7653B, light gold #DCAD75.
   Every tint below is one of them at low alpha; every ink is a
   token whose contrast §10.2 already checked. No new hex.
   ============================================================ */

.leadscreen {
    display: flex;
    flex-direction: column;
    gap: var(--s-5);
}

/* ---- Summary strip --------------------------------------- */

.summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-4);
    padding: var(--s-5) var(--s-6);
    border: 1px solid var(--hairline-soft);
    border-radius: var(--r-md);
    background: var(--paper);
    box-shadow: 0 1px 2px rgba(0, 37, 70, 0.04);
}

.summary__figure {
    display: flex;
    align-items: baseline;
    gap: var(--s-3);
}

/*
 * The one number allowed to be larger than the scale.
 *
 * It is a figure, not text: Latin numerals in the Latin face,
 * which is the one place a different size is information rather
 * than decoration.
 */
.summary__value {
    font-family: var(--font-body-en);
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.02em;
    color: var(--navy-900);
    direction: ltr;
}

.summary__label {
    font-size: var(--t-label);
    font-weight: 600;
    color: var(--text-muted);
}

.summary__note {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    padding: 7px var(--s-4);
    border-radius: var(--r-pill);
    background: rgba(0, 37, 70, 0.06);
    color: var(--navy-900);
    font-size: var(--t-meta);
    font-weight: 600;
}

.summary__clear {
    padding: 0;
    border: 0;
    background: none;
    color: var(--action-600);
    font: inherit;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
}

.summary__clear:hover {
    color: var(--navy-900);
}

.summary__acts {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-inline-start: auto;
}

/* ---- Search box ------------------------------------------- */

.searchbox {
    position: relative;
}

.searchbox__glyph {
    position: absolute;
    /* Clears the label sitting above the control. */
    inset-block-start: 38px;
    inset-inline-start: var(--s-4);
    color: var(--muted);
    pointer-events: none;
    z-index: 1;
}

.searchbox :deep(input) {
    padding-inline-start: var(--s-8);
}

.searchbox :deep(input:focus) ~ .searchbox__glyph,
.searchbox:focus-within .searchbox__glyph {
    color: var(--lavender-700);
}

/* ---- Filters --------------------------------------------- */

.filters {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

@media (min-width: 640px) {
    .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .filters__search { grid-column: 1 / -1; }
}

@media (min-width: 1024px) {
    .filters { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .filters__search { grid-column: span 2; }
}

/* ---- Archive note ---------------------------------------- */

.archnote {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    margin-block-end: var(--s-5);
    padding: var(--s-4);
    border-inline-start: 3px solid var(--gold-400);
    border-radius: var(--r-sm);
    background: rgba(220, 173, 117, 0.16);
    color: var(--navy-900);
    font-size: var(--t-label);
    line-height: 1.75;
}

.archnote :deep(.ico) { flex: 0 0 auto; }

/* ---- Table ----------------------------------------------- */

/*
 * 760, not 1000: two columns went to the side panel, so the table
 * fits a tablet without scrolling sideways at all — which was the
 * real cost of carrying source and interest here.
 */
.table { min-inline-size: 760px; }

/* This is the one table in the panel long enough to scroll its
   heading out of sight, so it is the one that pins it. */
.table thead th {
    position: sticky;
    inset-block-start: 0;
    z-index: 1;
}

/* Top, not middle: the message cell runs to several lines and the
   date beside it must start level with the first of them.
   Qualified, for the same reason as the chips below — it has to
   outscore `.shell .table td`, not merely tie with it. */
.leadscreen .table td { vertical-align: top; }

/* ---- Rows ------------------------------------------------- */

/* The whole row opens the drawer, so it has to say so. */
.row { cursor: pointer; }

/* An archived row still reads, but reads as put away. */
.row.is-archived { opacity: 0.6; }
.row.is-archived:hover { opacity: 1; }

/* ---- Cells ------------------------------------------------ */

.cell-when { white-space: nowrap; }

.when__date {
    display: block;
    font-weight: 600;
    color: var(--navy-900);
}

.when__time {
    display: block;
    margin-block-start: 3px;
    font-family: var(--font-mono);
    font-size: var(--t-meta);
    color: var(--muted);
    direction: ltr;
}

.cell-who { min-inline-size: 230px; }

/* The number that was captured and never shown. Secondary to the
   address above it, but a control rather than a caption — this is
   the thing a salesperson actually presses. */
.table__phone {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-block-start: 6px;
    color: var(--muted);
    font-size: var(--t-meta);
    direction: ltr;
    transition: color var(--dur-micro) var(--ease);
}

.table__phone:hover,
.table__phone:focus-visible { color: var(--action-600); }

.table__org {
    display: block;
    margin-block-start: 5px;
    color: var(--text-muted);
    font-size: var(--t-meta);
}

/* Cut at a character count with a visible «…», not at whatever
   number of lines happens to fit. See excerpt() in the script. */
/* The widest column now, which is the point: the message is the
   one field that is actually scanned. */
.cell-msg {
    min-inline-size: 280px;
    max-inline-size: 48ch;
    color: var(--text);
    line-height: 1.65;
}


/* ---- Status picker ---------------------------------------- *
 * Still a real <select> with the same handler; it simply stopped
 * looking like a form control dropped into a table. The colour is
 * on the wrapper, the caret is ours, and the native control sits
 * transparent on top so the keyboard behaviour is the browser's
 * own and not a reimplementation.                                */

.cell-status { white-space: nowrap; }

.statuspick {
    position: relative;
    display: inline-flex;
    align-items: center;
    border-radius: var(--r-pill);
    background: var(--tint);
    box-shadow: inset 0 0 0 1px var(--edge);
    transition: box-shadow var(--dur-micro) var(--ease);
}

.statuspick:hover { box-shadow: inset 0 0 0 1px var(--ink); }

.statuspick:focus-within {
    box-shadow: 0 0 0 3px rgba(134, 133, 216, 0.22);
}

/* Overrides the shared `.shell select` in admin.css: inside the
   table this is a badge, not a field. The scoped attribute already
   outweighs that rule, so it needs no :deep() to win. */
.table__select {
    appearance: none;
    min-block-size: 36px;
    padding-inline: var(--s-4) var(--s-7);
    border: 0;
    background: none;
    box-shadow: none;
    color: var(--ink);
    font-family: var(--font-body);
    font-size: var(--t-meta);
    font-weight: 700;
    cursor: pointer;
}

.table__select:focus-visible { outline: none; }

.statuspick__caret {
    position: absolute;
    inset-inline-end: var(--s-3);
    color: var(--ink);
    pointer-events: none;
    opacity: 0.75;
}

/*
 * One identity colour per state, and not one hex beyond the four.
 * Each tint is the brand colour at low alpha; each ink is the
 * token whose contrast §10.2 already checked.
 */
.statuspick--new {
    --tint: rgba(0, 37, 70, 0.08);
    --ink: var(--navy-700);
    --edge: rgba(0, 37, 70, 0.16);
}

.statuspick--contacted {
    --tint: rgba(134, 133, 216, 0.16);
    --ink: var(--lavender-700);
    --edge: rgba(95, 94, 190, 0.22);
}

.statuspick--qualified {
    --tint: rgba(220, 173, 117, 0.28);
    --ink: var(--action-600);
    --edge: rgba(220, 173, 117, 0.6);
}

.statuspick--won {
    --tint: rgba(30, 122, 90, 0.12);
    --ink: var(--success);
    --edge: rgba(30, 122, 90, 0.24);
}

.statuspick--lost {
    --tint: rgba(215, 101, 59, 0.12);
    --ink: var(--action-600);
    --edge: rgba(215, 101, 59, 0.26);
}

/* ---- Chips ------------------------------------------------ *
 * The shape is shared (admin.css). These are the states only this
 * screen has: where a lead stands, and whether it reached the CRM.
 * One identity colour each, no hex beyond the four.
 *
 * Qualified by `.leadscreen` on purpose. Bare `.chip--won` scores
 * the same as the shared `.shell .chip` it has to beat, and a tie
 * is settled by whichever stylesheet the bundler happens to emit
 * last — so every chip here would silently turn grey the day that
 * order changed. The extra class makes it win outright.          */

.leadscreen .chip--pending { background: rgba(220, 173, 117, 0.28); color: var(--action-600); }
.leadscreen .chip--synced  { background: rgba(30, 122, 90, 0.12);   color: var(--success); }
.leadscreen .chip--failed  { background: rgba(215, 101, 59, 0.14);  color: var(--action-600); }
/* «Not sent — no CRM» is a fact, not a fault: grey, not orange. */
.leadscreen .chip--none,
.leadscreen .chip--not_connected { background: var(--paper-alt); color: var(--muted); }

.leadscreen .chip--st-new       { background: rgba(0, 37, 70, 0.08);     color: var(--navy-700); }
.leadscreen .chip--st-contacted { background: rgba(134, 133, 216, 0.16); color: var(--lavender-700); }
.leadscreen .chip--st-qualified { background: rgba(220, 173, 117, 0.28); color: var(--action-600); }
.leadscreen .chip--st-won       { background: rgba(30, 122, 90, 0.12);   color: var(--success); }
.leadscreen .chip--st-lost      { background: rgba(215, 101, 59, 0.12);  color: var(--action-600); }

/* ---- Actions ---------------------------------------------- *
 * The buttons themselves are shared (admin.css). This is the
 * column they sit in: shrink-to-fit, and never wrapped.          */

.rowact { inline-size: 1%; white-space: nowrap; }

/* ---- Detail drawer ---------------------------------------- */

.drawer {
    position: fixed;
    inset-block: 0;
    inset-inline-end: 0;
    z-index: 70;
    display: flex;
    flex-direction: column;
    inline-size: min(480px, 100%);
    background: var(--paper);
    border-inline-start: 1px solid var(--hairline);
    box-shadow: -24px 0 60px rgba(0, 37, 70, 0.16);
}

html[dir='ltr'] .drawer {
    box-shadow: 24px 0 60px rgba(0, 37, 70, 0.16);
}

.drawer__head {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-5) var(--s-6);
    background: var(--navy-900);
}

/*
 * The colour is set here and not left to inherit, which is why the
 * title was invisible: base.css gives every h1–h4 `color:
 * var(--navy-900)`, and an element's own rule beats the colour it
 * would otherwise inherit from `.drawer__head`. The heading was
 * navy on navy.
 */
.drawer__title {
    margin: 0;
    color: #fff;
    font-family: var(--font-body);
    font-size: var(--t-title);
    font-weight: 700;
    line-height: 1.45;
    letter-spacing: 0;
    overflow-wrap: anywhere;
}

.drawer__close {
    margin-inline-start: auto;
    flex: 0 0 auto;
    inline-size: 40px;
    min-block-size: 40px;
    border: 0;
    border-radius: var(--r-sm);
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: var(--t-body);
    cursor: pointer;
    transition: background-color var(--dur-micro) var(--ease);
}

.drawer__close:hover { background: rgba(255, 255, 255, 0.22); }

.drawer__body {
    flex: 1;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: var(--s-6);
}

/* A section heading inside the drawer — Arabic, so the Arabic
   face, no tracking, no uppercase. */
.drawer__sub {
    margin-block: var(--s-7) var(--s-4);
    padding-block-end: var(--s-2);
    border-block-end: 1px solid var(--hairline-soft);
    font-family: var(--font-body);
    font-size: var(--t-label);
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
    color: var(--navy-900);
}

/* Label above value, not beside it: an Arabic label and a Latin
   URL in two columns leaves both cramped and neither aligned. */
.pairs {
    display: grid;
    gap: var(--s-4);
    margin: 0;
}

.pairs dt {
    font-size: var(--t-meta);
    font-weight: 600;
    color: var(--muted);
}

.pairs dd {
    margin: 5px 0 0;
    color: var(--text);
    font-size: var(--t-body);
    line-height: 1.75;
    overflow-wrap: break-word;
}

.ltr {
    direction: ltr;
    text-align: start;
    unicode-bidi: isolate;
}

.drawer__note {
    margin-block-start: var(--s-4);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
    color: var(--text-muted);
    font-size: var(--t-meta);
    line-height: 1.85;
}

.drawer__crm {
    margin-block-end: var(--s-4);
    font-size: var(--t-body);
    font-weight: 600;
}

.logs {
    margin-block-start: var(--s-5);
    list-style: none;
    padding: 0;
}

.logs__row {
    display: grid;
    grid-template-columns: auto auto auto minmax(0, 1fr);
    gap: var(--s-3);
    align-items: baseline;
    padding-block: var(--s-3);
    border-block-start: 1px solid var(--hairline-soft);
    font-size: var(--t-meta);
    color: var(--text-muted);
}

.logs__error {
    color: var(--action-600);
    overflow-wrap: anywhere;
}

/* §10.7. The shared elements are zeroed in admin.css; these three
   are this screen's own and have to be named here. */
@media (prefers-reduced-motion: reduce) {
    .table__phone,
    .statuspick,
    .drawer__close {
        transition: none;
    }
}

/* ---- Small screens ---------------------------------------- *
 * The table keeps its own horizontal scroll rather than being
 * rebuilt as cards: an operator comparing leads needs the columns
 * to stay columns. What changes is everything around it.        */

@media (max-width: 767px) {
    .summary {
        padding: var(--s-4);
        gap: var(--s-3);
    }

    .summary__value { font-size: 1.5rem; }

    .summary__acts {
        inline-size: 100%;
        margin-inline-start: 0;
    }

    .summary__acts > * {
        flex: 1 1 auto;
        justify-content: center;
    }

    .drawer {
        inline-size: 100%;
        border-inline-start: 0;
    }

    .drawer__body { padding: var(--s-5); }
}
</style>
