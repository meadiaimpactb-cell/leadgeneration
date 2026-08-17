<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
import Field from '@/Components/admin/Field.vue';
import { confirmDialog } from '@/admin/confirm';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Header and footer menus (§9.1).
 *
 * Each menu saves as one list, so reordering, renaming and removing is a
 * single gesture and can never leave a half-applied menu on the site.
 */
const props = defineProps({
    navigations: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const DIRS = { ar: 'rtl', en: 'ltr' };

const state = reactive(
    Object.fromEntries(
        props.navigations.map((nav) => [
            nav.id,
            nav.items.map((item) => ({
                id: item.id,
                url: item.url ?? '',
                isActive: item.isActive,
                labels: { ...item.labels },
            })),
        ])
    )
);

function addItem(navId) {
    state[navId].push({
        id: null,
        url: '',
        isActive: true,
        labels: Object.fromEntries(props.locales.map((l) => [l, ''])),
    });
}

async function removeItem(navId, index) {
    if (!(await confirmDialog({ message: t('admin.confirm_delete') }))) return;

    state[navId].splice(index, 1);
}

function move(navId, index, delta) {
    const next = index + delta;
    if (next < 0 || next >= state[navId].length) return;

    const list = state[navId];
    [list[index], list[next]] = [list[next], list[index]];
}

function save(navId) {
    router.put(`/admin/navigation/${navId}`, { items: state[navId] }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="t('admin.navigation')">
        <Panel v-for="nav in navigations" :key="nav.id" :title="nav.key">
            <template #actions>
                <button class="act btn btn--ghost" type="button" @click="addItem(nav.id)">
                    <NavIcon name="plus" :size="18" :muted="false" />
                    <span>{{ t('admin.create') }}</span>
                </button>
                <button class="act btn btn--cta" type="button" @click="save(nav.id)">
                    <NavIcon name="check" :size="18" :muted="false" />
                    <span>{{ t('admin.save') }}</span>
                </button>
            </template>

            <p v-if="!state[nav.id].length" class="empty">{{ t('admin.no_records') }}</p>

            <ol v-else class="items">
                <li v-for="(item, index) in state[nav.id]" :key="index" class="item">
                    <div class="item__fields">
                        <Field
                            v-for="locale in locales"
                            :key="locale"
                            v-model="item.labels[locale]"
                            :label="`${t('admin.navigation')} · ${locale.toUpperCase()}`"
                            :dir="DIRS[locale]"
                        />
                        <Field v-model="item.url" :label="t('admin.field_url')" dir="ltr" required />
                        <Field v-model="item.isActive" :label="t('admin.active')" type="checkbox" />
                    </div>

                    <div class="item__tools">
                        <button
                            class="btn btn--ghost act act--icon"
                            type="button"
                            :title="t('admin.move_up')"
                            :aria-label="t('admin.move_up')"
                            :disabled="index === 0"
                            @click="move(nav.id, index, -1)"
                        ><NavIcon name="publish" :size="18" :muted="false" /></button>
                        <button
                            class="btn btn--ghost act act--icon"
                            type="button"
                            :title="t('admin.move_down')"
                            :aria-label="t('admin.move_down')"
                            :disabled="index === state[nav.id].length - 1"
                            @click="move(nav.id, index, 1)"
                        ><NavIcon name="unpublish" :size="18" :muted="false" /></button>
                        <button class="btn btn--ghost danger act act--icon" type="button" @click="removeItem(nav.id, index)"
                                    :title="t('admin.delete')"
                                    :aria-label="t('admin.delete')"><NavIcon name="trash" :size="18" :muted="false" /></button>
                    </div>
                </li>
            </ol>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.items {
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
}

.item {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    padding: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.item__fields {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: 1fr;
    flex: 1;
    min-inline-size: 0;
}

.item__tools {
    display: flex;
    gap: var(--s-1);
    align-items: start;
}

.item__tools .btn {
    min-inline-size: 44px;
    min-block-size: 44px;
    padding-inline: var(--s-2);
    font-size: var(--fs-xs);
}

.danger {
    color: var(--action-600);
}

.empty {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1024px) {
    .item__fields {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-items: end;
    }
}
</style>
