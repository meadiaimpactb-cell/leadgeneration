<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * Admin/Crm (§6.3) — 2.2.
 *
 * Two providers, their credentials, a connection test, the state of the
 * queue, and one button that re-queues everything that never arrived.
 */
const props = defineProps({
    driver: { type: String, required: true },
    providers: { type: Array, default: () => [] },
    fields: { type: Object, default: () => ({}) },
    credentials: { type: Object, default: () => ({}) },
    sources: { type: Object, default: () => ({}) },
    status: { type: Object, required: true },
    log: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { number, dateTime } = useFormat();

const form = useForm({
    driver: props.driver,
    credentials: JSON.parse(JSON.stringify(props.credentials)),
});

const stuck = computed(() => (props.status.pending ?? 0) + (props.status.failed ?? 0));

function save() {
    form.put('/admin/integrations/crm', { preserveScroll: true });
}

function test() {
    router.post('/admin/integrations/crm/test', {}, { preserveScroll: true });
}

async function resyncAll() {
    if (!(await confirmDialog({ message: t('admin.crm_resync_confirm') }))) return;

    router.post('/admin/integrations/crm/resync-all', {}, { preserveScroll: true });
}

const sourceLabel = (provider, field) => t(`admin.crm_source_${props.sources[provider]?.[field] ?? 'unset'}`);

/**
 * The control each credential kind gets. A `token` is a secret, but showing it
 * as a password field made a 1,200-character JWT impossible to read back — so
 * it gets a growing box instead. It is still never sent back to the browser:
 * a saved token comes down as bullets, and only what is typed in this session
 * is visible.
 */
const controlFor = (kind) => (kind === 'token' ? 'textarea' : kind === 'secret' ? 'password' : 'text');

/**
 * Some credentials are copied from the provider's dashboard; others only ever
 * come out of an authorisation exchange and cannot be found on any page.
 *
 * The generic "paste it as the provider gives it" note is true of the first
 * kind and actively misleading about the second — it led to an authorisation
 * code being pasted into the OAuth token box, which fails as an opaque 401.
 * So the fields that are not pasted say so themselves.
 */
const FIELD_NOTES = {
    'zid.access_token': 'admin.crm_hint_zid_manager',
    'zid.oauth_token': 'admin.crm_hint_zid_oauth',
};

const hintFor = (provider, field, kind) => {
    const source = sourceLabel(provider, field);
    const note = FIELD_NOTES[`${provider}.${field}`];

    if (note) return `${source} — ${t(note)}`;

    return kind === 'token' ? `${source} — ${t('admin.crm_token_hint')}` : source;
};
</script>

<template>
    <AdminLayout :title="t('admin.crm_link')">
        <Panel :title="t('admin.crm_provider')" :hint="t('admin.crm_provider_hint')">
            <!-- Said out loud rather than left to be inferred from two unticked
                 radios: until a provider is chosen the enquiries are kept here
                 and emailed, and nothing leaves for any external system. -->
            <p v-if="!providers.includes(driver)" class="notlinked">
                {{ t('admin.crm_not_linked') }}
            </p>

            <ul class="providers">
                <li v-for="provider in providers" :key="provider">
                    <label class="providers__row">
                        <input v-model="form.driver" type="radio" :value="provider" />
                        <span class="providers__name latin">{{ provider }}</span>
                    </label>
                </li>
            </ul>

            <!--
                Only the chosen provider's fields are in the page, rather than
                all of them with the others hidden. The token box measures
                itself when it mounts, and an element inside a `display: none`
                block has no height to measure; hiding the rest also left their
                inputs reachable by keyboard while invisible on screen. Nothing
                typed is lost by unmounting — the values live in
                `form.credentials`, not in the DOM.
            -->
            <div v-if="fields[form.driver]" :key="`f-${form.driver}`" class="creds">
                <!--
                    A `token` is a secret too, but it is a JWT of a thousand
                    characters and more. In a single-line password box it was a
                    value nobody could read back, check the end of, or tell
                    apart from a truncated paste — so it gets a box that grows
                    with it, the way the contact form's message field does.
                -->
                <Field
                    v-for="(kind, field) in fields[form.driver]"
                    :key="field"
                    v-model="form.credentials[form.driver][field]"
                    :label="field.replace(/_/g, ' ')"
                    :type="controlFor(kind)"
                    :autogrow="kind === 'token'"
                    :rows="1"
                    dir="ltr"
                    :hint="hintFor(form.driver, field, kind)"
                    :error="form.errors[`credentials.${form.driver}.${field}`]"
                />
            </div>

            <div class="bar">
                <button class="act btn btn--cta" type="button" :disabled="form.processing" @click="save">
                    <NavIcon name="check" :size="18" :muted="false" />
                    <span>{{ form.processing ? t('admin.saving') : t('admin.save') }}</span>
                </button>
                <button class="btn btn--secondary" type="button" @click="test">
                    {{ t('admin.crm_test') }}
                </button>
            </div>
        </Panel>

        <Panel :title="t('admin.crm_status')">
            <ul class="tiles">
                <li class="tile">
                    <p class="tile__label">{{ t('admin.crm_synced') }}</p>
                    <p class="tile__value tabular">{{ number(status.synced) }}</p>
                </li>
                <li class="tile">
                    <p class="tile__label">{{ t('admin.crm_pending') }}</p>
                    <p class="tile__value tabular">{{ number(status.pending) }}</p>
                </li>
                <li class="tile tile--accent">
                    <p class="tile__label">{{ t('admin.crm_failed') }}</p>
                    <p class="tile__value tabular">{{ number(status.failed) }}</p>
                </li>
                <li class="tile">
                    <p class="tile__label">{{ t('admin.crm_last_success') }}</p>
                    <p class="tile__value tile__value--small">
                        {{ status.lastSuccessAt ? dateTime(status.lastSuccessAt) : '—' }}
                    </p>
                </li>
            </ul>

            <div class="bar">
                <button class="btn btn--cta" type="button" :disabled="!stuck" @click="resyncAll">
                    {{ t('admin.crm_resync_all', { count: number(stuck) }) }}
                </button>
                <span v-if="!stuck" class="ok">{{ t('admin.crm_nothing_stuck') }}</span>
            </div>
        </Panel>

        <Panel :title="t('admin.crm_log')" :hint="t('admin.crm_log_hint')">
            <p v-if="!log.length" class="empty">{{ t('admin.no_records') }}</p>

            <ul v-else class="log">
                <li v-for="row in log" :key="row.id" class="log__row">
                    <span class="log__state" :class="row.successful ? 'is-ok' : 'is-bad'">
                        {{ row.successful ? t('admin.crm_ok') : t('admin.crm_error') }}
                    </span>
                    <span class="log__lead tabular">#{{ number(row.leadId) }}</span>
                    <span class="log__provider latin">{{ row.provider }}</span>
                    <span class="log__attempt tabular">{{ number(row.attempt) }}</span>
                    <span class="log__at">{{ dateTime(row.at) }}</span>
                    <span class="log__error">{{ row.error ?? '—' }}</span>
                </li>
            </ul>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.notlinked {
    margin-block-end: var(--s-4);
    padding: var(--s-3);
    border-inline-start: 3px solid var(--action-600);
    background: var(--gold-100);
    border-radius: var(--r-sm);
    font-size: var(--fs-sm);
    line-height: 1.7;
    color: var(--navy-900);
}

.providers {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-5);
    list-style: none;
    margin-block-end: var(--s-5);
}

.providers__row {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    cursor: pointer;
}

.providers__name {
    font-weight: 600;
    text-transform: uppercase;
}

.creds {
    display: grid;
    gap: var(--s-4);
}

.bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

.ok {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

.tiles {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--gutter);
    list-style: none;
}

.tile {
    padding: var(--s-4);
    border-radius: var(--r-md);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.tile__label {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.tile__value {
    margin-block-start: var(--s-2);
    font-size: var(--fs-h2);
    font-weight: 600;
    color: var(--navy-900);
    line-height: 1;
}

.tile__value--small {
    font-size: var(--fs-body);
    font-weight: 500;
}

.tile--accent .tile__value {
    color: var(--action-600);
}

.log {
    display: grid;
    gap: var(--s-2);
    list-style: none;
}

.log__row {
    display: grid;
    grid-template-columns: 5rem 4rem 1fr;
    gap: var(--s-3);
    align-items: baseline;
    padding-block: var(--s-2);
    border-block-end: var(--border-hairline);
    font-size: var(--fs-sm);
}

.log__state {
    font-weight: 700;
}

.log__state.is-ok {
    color: var(--success);
}

.log__state.is-bad {
    color: var(--action-600);
}

.log__provider,
.log__attempt,
.log__at,
.log__error {
    color: var(--text-muted);
}

.log__error {
    grid-column: 1 / -1;
    word-break: break-word;
}

.empty {
    color: var(--text-muted);
}

@media (min-width: 900px) {
    .tiles {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .log__row {
        grid-template-columns: 5rem 4rem 5rem 3rem 11rem minmax(0, 1fr);
    }

    .log__error {
        grid-column: auto;
    }
}
</style>
