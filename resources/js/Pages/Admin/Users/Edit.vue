<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import { useTranslation } from '@/Composables/useTranslation';

const props = defineProps({
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    is_active: props.user?.isActive ?? true,
    roles: props.user?.roles ?? [],
});

function toggleRole(role) {
    form.roles = form.roles.includes(role)
        ? form.roles.filter((r) => r !== role)
        : [...form.roles, role];
}

function submit() {
    if (props.user) {
        form.patch(`/admin/users/${props.user.id}`, { preserveScroll: true, onSuccess: () => form.reset('password') });
    } else {
        form.post('/admin/users');
    }
}
</script>

<template>
    <AdminLayout :title="user ? user.name : t('admin.create')">
        <form @submit.prevent="submit">
            <Panel :title="t('admin.users')">
                <div class="grid">
                    <Field v-model="form.name" :label="t('admin.users')" :error="form.errors.name" required />
                    <Field
                        v-model="form.email"
                        :label="t('admin.email')"
                        type="email"
                        dir="ltr"
                        :error="form.errors.email"
                        required
                    />
                    <Field
                        v-model="form.password"
                        :label="t('admin.password')"
                        type="password"
                        dir="ltr"
                        :error="form.errors.password"
                        :required="!user"
                    />
                    <Field v-model="form.is_active" :label="t('admin.active')" type="checkbox" />
                </div>
            </Panel>

            <Panel :title="t('admin.roles')">
                <p v-if="form.errors.roles" class="err">{{ form.errors.roles }}</p>

                <ul class="roles">
                    <li v-for="role in roles" :key="role">
                        <label class="roles__item">
                            <input
                                type="checkbox"
                                :checked="form.roles.includes(role)"
                                @change="toggleRole(role)"
                            />
                            <span>{{ t(`admin.role_${role}`) }}</span>
                        </label>
                    </li>
                </ul>
            </Panel>

            <div class="bar">
                <button class="btn btn--cta" type="submit" :disabled="form.processing">
                    {{ form.processing ? t('admin.saving') : t('admin.save') }}
                </button>
                <Link href="/admin/users" class="btn btn--ghost">{{ t('admin.cancel') }}</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.grid {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

.roles {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-4);
}

.roles__item {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    font-size: var(--fs-sm);
}

.err {
    margin-block-end: var(--s-3);
    color: var(--action-600);
    font-size: var(--fs-sm);
    font-weight: 600;
}

.bar {
    display: flex;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

@media (min-width: 640px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
        align-items: end;
    }
}
</style>
