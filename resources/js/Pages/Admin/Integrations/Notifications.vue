<script setup>
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import Field from '@/Components/admin/Field.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Who the site tells, and what it says (§6.2 step 3, §20 decision 4).
 *
 * Two warnings sit above the list and neither is decoration. The first appears
 * only while no recipient has been added, because an empty list does not mean
 * nobody is being alerted — the server's .env still answers — and an operator
 * who assumes otherwise will either duplicate the address or clear the
 * variable. The second is about the queue: mail here is queued so the visitor
 * never waits on a mail server, which also means a stopped worker delivers
 * nothing while every screen still reads as normal.
 */
const props = defineProps({
    recipients: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    template: { type: Object, default: () => ({}) },
    envFallback: { type: Array, default: () => [] },
    queueDriver: { type: String, default: 'sync' },
});

const { t } = useTranslation();

const state = reactive(
    props.recipients.map((r) => ({
        id: r.id,
        email: r.email,
        name: r.name ?? '',
        isActive: r.isActive,
        events: { ...r.events },
    }))
);

const template = reactive({ ...props.template });

const fresh = reactive({ email: '', name: '' });
const adding = ref(false);

/**
 * Nobody is subscribed to the arrival of an enquiry.
 *
 * Distinct from an empty list: here somebody HAS configured this screen, so
 * the .env fallback no longer applies and a lead genuinely reaches no inbox.
 * That is the failure this whole screen exists to prevent, so it is said out
 * loud rather than left to be inferred from unchecked boxes.
 */
const nobodyOnNewLead = computed(
    () => state.length > 0 && !state.some((r) => r.isActive && r.events.new_lead)
);

function add() {
    if (!fresh.email.trim()) return;
    adding.value = true;
    router.post(
        '/admin/integrations/notifications',
        { email: fresh.email.trim(), name: fresh.name.trim() || null },
        {
            preserveScroll: true,
            onSuccess: () => {
                fresh.email = '';
                fresh.name = '';
            },
            onFinish: () => (adding.value = false),
        }
    );
}

async function remove(id) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;

    router.delete(`/admin/integrations/notifications/${id}`, { preserveScroll: true });
}

function save() {
    router.put(
        '/admin/integrations/notifications',
        { recipients: state, template },
        { preserveScroll: true }
    );
}
</script>

<template>
    <AdminLayout :title="t('admin.notifications')">
        <p v-if="envFallback.length" class="notice" role="note">
            {{ t('admin.notify_env_fallback', { list: envFallback.join('، ') }) }}
        </p>

        <p v-if="nobodyOnNewLead" class="notice notice--alarm" role="alert">
            {{ t('admin.notify_nobody_on_new_lead') }}
        </p>

        <p v-if="queueDriver === 'sync'" class="notice" role="note">
            {{ t('admin.notify_queue_sync') }}
        </p>

        <Panel :title="t('admin.notify_recipients')" :hint="t('admin.notify_recipients_hint')">
            <template #actions>
                <button class="act btn btn--cta" type="button" @click="save">
                    <NavIcon name="check" :size="18" :muted="false" />
                    <span>{{ t('admin.save') }}</span>
                </button>
            </template>

            <p v-if="!state.length" class="empty">{{ t('admin.notify_empty') }}</p>

            <ol v-else class="rows">
                <li v-for="row in state" :key="row.id" class="row">
                    <header class="row__head">
                        <span class="row__email latin" dir="ltr">{{ row.email }}</span>

                        <button
                            class="btn btn--ghost row__remove"
                            type="button"
                            :aria-label="t('admin.delete')"
                            @click="remove(row.id)"
                        >×</button>
                    </header>

                    <div class="row__grid">
                        <Field v-model="row.name" :label="t('admin.notify_name')" />
                        <Field v-model="row.isActive" :label="t('admin.notify_active')" type="checkbox" />
                    </div>

                    <fieldset class="row__events" :disabled="!row.isActive">
                        <legend class="row__legend">{{ t('admin.notify_events') }}</legend>
                        <Field
                            v-for="event in events"
                            :key="event"
                            v-model="row.events[event]"
                            :label="t(`admin.notify_event_${event}`)"
                            :hint="t(`admin.notify_event_${event}_hint`)"
                            type="checkbox"
                        />
                    </fieldset>
                </li>
            </ol>
        </Panel>

        <Panel :title="t('admin.notify_add')" :hint="t('admin.notify_add_hint')">
            <div class="row__grid">
                <Field v-model="fresh.email" :label="t('admin.notify_email')" type="email" dir="ltr" />
                <Field v-model="fresh.name" :label="t('admin.notify_name')" />
            </div>
            <button class="btn btn--cta" type="button" :disabled="adding" @click="add">
                {{ t('admin.notify_add') }}
            </button>
        </Panel>

        <Panel :title="t('admin.notify_template')" :hint="t('admin.notify_template_hint')">
            <template #actions>
                <button class="act btn btn--cta" type="button" @click="save">
                    <NavIcon name="check" :size="18" :muted="false" />
                    <span>{{ t('admin.save') }}</span>
                </button>
            </template>

            <Field
                v-model="template['notifications.alert_subject']"
                :label="t('admin.notify_alert_subject')"
                :hint="t('admin.notify_alert_subject_hint')"
            />
            <Field
                v-model="template['notifications.alert_intro']"
                :label="t('admin.notify_alert_intro')"
                :hint="t('admin.notify_alert_intro_hint')"
                type="textarea"
            />
            <Field
                v-model="template['notifications.summary_subject']"
                :label="t('admin.notify_summary_subject')"
                :hint="t('admin.notify_summary_subject_hint')"
            />
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.notice {
    margin-block-end: var(--s-5);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--action-600);
    max-inline-size: 80ch;
}

.notice--alarm {
    background: var(--navy-100);
    color: var(--navy-900);
    font-weight: 700;
}

.empty {
    color: var(--text-muted);
}

.rows {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}

.row {
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.row__head {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    margin-block-end: var(--s-4);
}

.row__email {
    font-weight: 700;
    color: var(--navy-900);
}

.row__remove {
    margin-inline-start: auto;
    min-inline-size: 44px;
    min-block-size: 44px;
}

.row__grid {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
    margin-block-end: var(--s-4);
}

.row__events {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: 1fr;
    border: 0;
    padding: 0;
    margin: 0;
}

.row__events[disabled] {
    opacity: 0.5;
}

.row__legend {
    font-size: var(--fs-sm);
    font-weight: 700;
    color: var(--navy-900);
    margin-block-end: var(--s-2);
}

@media (min-width: 640px) {
    .row__grid {
        grid-template-columns: repeat(2, 1fr);
        align-items: end;
    }

    .row__events {
        grid-template-columns: repeat(3, 1fr);
    }
}
</style>
