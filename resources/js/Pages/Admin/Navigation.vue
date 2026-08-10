<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
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

function removeItem(navId, index) {
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
                <button class="btn btn--ghost" type="button" @click="addItem(nav.id)">
                    {{ t('admin.create') }}
                </button>
                <button class="btn btn--cta" type="button" @click="save(nav.id)">
                    {{ t('admin.save') }}
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
                        <Field v-model="item.url" label="url" dir="ltr" required />
                        <Field v-model="item.isActive" :label="t('admin.active')" type="checkbox" />
                    </div>

                    <div class="item__tools">
                        <button
                            class="btn btn--ghost"
                            type="button"
                            :aria-label="t('admin.move_up')"
                            :disabled="index === 0"
                            @click="move(nav.id, index, -1)"
                        >↑</button>
                        <button
                            class="btn btn--ghost"
                            type="button"
                            :aria-label="t('admin.move_down')"
                            :disabled="index === state[nav.id].length - 1"
                            @click="move(nav.id, index, 1)"
                        >↓</button>
                        <button class="btn btn--ghost danger" type="button" @click="removeItem(nav.id, index)">
                            {{ t('admin.delete') }}
                        </button>
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
        grid-template-columns: repeat(4, 1fr);
        align-items: end;
    }
}
</style>
