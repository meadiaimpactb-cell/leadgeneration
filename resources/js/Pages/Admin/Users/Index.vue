<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';
import { useFormat } from '@/Composables/useFormat';

defineProps({
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { dateTime } = useFormat();

async function disable(user) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;
    router.delete(`/admin/users/${user.id}`);
}

// Timestamps go through useFormat: Gregorian calendar, Latin digits.
</script>

<template>
    <AdminLayout :title="t('admin.users')">
        <Panel :title="t('admin.users')">
            <template #actions>
                <Link href="/admin/users/create" class="act btn btn--cta">
                    <NavIcon name="plus" :size="18" :muted="false" />
                    <span>{{ t('admin.create') }}</span>
                </Link>
            </template>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ t('admin.users') }}</th>
                            <th>{{ t('admin.email') }}</th>
                            <th>{{ t('admin.roles') }}</th>
                            <th>{{ t('admin.active') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td>
                                <Link :href="`/admin/users/${user.id}/edit`" class="table__link">
                                    {{ user.name }}
                                </Link>
                            </td>
                            <td class="latin">{{ user.email }}</td>
                            <td>
                                <span v-for="role in user.roles" :key="role" class="chip">
                                    {{ t(`admin.role_${role}`) }}
                                </span>
                            </td>
                            <td>
                                <span class="chip" :class="user.isActive ? 'chip--ok' : 'chip--warn'">
                                    {{ user.isActive ? t('admin.active') : t('admin.inactive') }}
                                </span>
                                <p class="muted">{{ dateTime(user.lastLoginAt) }}</p>
                            </td>
                            <td>
                                <button
                                    v-if="user.isActive"
                                    class="btn btn--ghost danger act act--icon"
                                    type="button"
                                    @click="disable(user)"
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
    display: inline-block;
    margin-inline-end: var(--s-1);
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

.chip--warn {
    background: var(--gold-100);
    color: var(--action-600);
}

.muted {
    margin-block-start: var(--s-1);
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.danger {
    color: var(--action-600);
}
</style>
