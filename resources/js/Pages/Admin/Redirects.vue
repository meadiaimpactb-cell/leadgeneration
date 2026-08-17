<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import Field from '@/Components/admin/Field.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

/**
 * Managed 301s (§13, §22.7).
 *
 * Every path that changes gets a row here, so a link that has been shared,
 * printed or indexed keeps working. The hit count shows which old URLs still
 * carry traffic.
 */
const props = defineProps({
    redirects: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { number } = useFormat();

const STATUSES = [
    { value: 301, label: '301 — دائم' },
    { value: 302, label: '302 — مؤقت' },
    { value: 307, label: '307' },
    { value: 308, label: '308' },
];

const state = reactive(props.redirects.map((r) => ({ ...r })));

function add() {
    state.push({ id: null, from: '', to: '', status: 301, hits: 0, isActive: true });
}

async function remove(index) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete_redirect') }))) return;

    state.splice(index, 1);
}

function save() {
    router.put('/admin/redirects', { redirects: state }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="t('admin.redirects')">
        <p class="notice">{{ t('admin.redirects_hint') }}</p>

        <Panel :title="t('admin.redirects')">
            <template #actions>
                <button class="act btn btn--ghost" type="button" @click="add">
                    <NavIcon name="plus" :size="18" :muted="false" />
                    <span>{{ t('admin.create') }}</span>
                </button>
                <button class="act btn btn--cta" type="button" @click="save">
                    <NavIcon name="check" :size="18" :muted="false" />
                    <span>{{ t('admin.save') }}</span>
                </button>
            </template>

            <p v-if="!state.length" class="empty">{{ t('admin.no_records') }}</p>

            <ul v-else class="rows">
                <li v-for="(row, index) in state" :key="index" class="row">
                    <Field v-model="row.from" :label="t('admin.redirect_from')" dir="ltr" required />
                    <Field v-model="row.to" :label="t('admin.redirect_to')" dir="ltr" required />
                    <Field
                        v-model="row.status"
                        :label="t('admin.redirect_status')"
                        type="select"
                        :options="STATUSES"
                    />
                    <Field v-model="row.isActive" :label="t('admin.active')" type="checkbox" />

                    <div class="row__meta">
                        <span class="row__hits tabular">
                            {{ t('admin.redirect_hits') }}: {{ number(row.hits) }}
                        </span>
                        <button class="btn btn--ghost danger act act--icon" type="button" @click="remove(index)"
                                    :title="t('admin.delete')"
                                    :aria-label="t('admin.delete')"><NavIcon name="trash" :size="18" :muted="false" /></button>
                    </div>
                </li>
            </ul>
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
    font-size: var(--fs-sm);
    max-inline-size: 80ch;
}

.rows {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}

.row {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: 1fr;
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.row__meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
}

.row__hits {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.danger {
    color: var(--action-600);
}

.empty {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1024px) {
    .row {
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.4fr) minmax(0, 0.8fr) minmax(0, 0.5fr) minmax(0, 0.9fr);
        align-items: end;
    }
}
</style>
