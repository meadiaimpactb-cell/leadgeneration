<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The list screen shared by every content entity (§9.1).
 *
 * Which entity it is showing comes from the route; what fields it has comes
 * from ContentRegistry on the server.
 */
const props = defineProps({
    entity: { type: String, required: true },
    records: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
});

const { t } = useTranslation();
const dragging = ref(null);

function title() {
    // Entity keys match the admin.* language keys, e.g. "product-categories".
    return t(`admin.${props.entity.replace(/-/g, '_')}`);
}

function destroy(record) {
    if (!confirm(t('admin.confirm_delete'))) return;
    router.delete(`/admin/content/${props.entity}/${record.id}`);
}

function persistOrder(order) {
    router.post(`/admin/content/${props.entity}/reorder`, { order }, { preserveScroll: true });
}

function move(index, delta) {
    const next = index + delta;
    if (next < 0 || next >= props.records.length) return;

    const ids = props.records.map((r) => r.id);
    [ids[index], ids[next]] = [ids[next], ids[index]];
    persistOrder(ids);
}

function onDrop(index) {
    if (dragging.value === null || dragging.value === index) return;

    const ids = props.records.map((r) => r.id);
    const [moved] = ids.splice(dragging.value, 1);
    ids.splice(index, 0, moved);
    dragging.value = null;
    persistOrder(ids);
}
</script>

<template>
    <AdminLayout :title="title()">
        <Panel :title="t('admin.entity_records')" :hint="t('admin.order_hint')">
            <template #actions>
                <Link
                    v-if="meta.creatable"
                    :href="`/admin/content/${entity}/create`"
                    class="btn btn--cta"
                >
                    {{ t('admin.create') }}
                </Link>
            </template>

            <p v-if="!records.length" class="empty">{{ t('admin.no_records') }}</p>

            <ol v-else class="rows">
                <li
                    v-for="(record, index) in records"
                    :key="record.id"
                    class="row"
                    draggable="true"
                    @dragstart="dragging = index"
                    @dragover.prevent
                    @drop.prevent="onDrop(index)"
                >
                    <div class="row__main">
                        <Link :href="`/admin/content/${entity}/${record.id}/edit`" class="row__title">
                            {{ record.title }}
                        </Link>
                        <span v-if="record.slug" class="row__slug latin">{{ record.slug }}</span>

                        <span v-if="!record.active" class="chip">{{ t('admin.inactive') }}</span>
                        <span
                            v-for="loc in locales.filter((l) => !record.locales.includes(l))"
                            :key="loc"
                            class="chip chip--warn"
                        >
                            {{ t('admin.translation_missing') }}: {{ loc }}
                        </span>
                    </div>

                    <div class="row__tools">
                        <Link
                            v-if="meta.hasSections"
                            :href="`/admin/sections/${entity === 'sectors' ? 'sector' : 'solution'}/${record.id}`"
                            class="btn btn--ghost"
                        >
                            {{ t('admin.sections') }}
                        </Link>
                        <button
                            class="btn btn--ghost"
                            type="button"
                            :aria-label="t('admin.move_up')"
                            :disabled="index === 0"
                            @click="move(index, -1)"
                        >↑</button>
                        <button
                            class="btn btn--ghost"
                            type="button"
                            :aria-label="t('admin.move_down')"
                            :disabled="index === records.length - 1"
                            @click="move(index, 1)"
                        >↓</button>
                        <button
                            v-if="meta.deletable"
                            class="btn btn--ghost danger"
                            type="button"
                            @click="destroy(record)"
                        >
                            {{ t('admin.delete') }}
                        </button>
                    </div>
                </li>
            </ol>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.rows {
    display: flex;
    flex-direction: column;
    gap: var(--s-2);
}

.row {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    align-items: center;
    padding: var(--s-3);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.row__main {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--s-2);
    flex: 1;
    min-inline-size: 0;
}

.row__title {
    font-weight: 600;
    color: var(--link);
}

.row__slug {
    font-size: var(--fs-xs);
    color: var(--text-muted);
}

.row__tools {
    display: flex;
    gap: var(--s-1);
    margin-inline-start: auto;
}

.row__tools .btn {
    min-inline-size: 44px;
    min-block-size: 44px;
    padding-inline: var(--s-2);
    font-size: var(--fs-xs);
}

.chip {
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    font-size: var(--fs-xs);
    font-weight: 600;
    background: var(--navy-100);
    color: var(--navy-900);
}

.chip--warn {
    background: var(--gold-100);
    color: var(--action-600);
}

.danger {
    color: var(--action-600);
}

.empty {
    color: var(--text-muted);
    font-size: var(--fs-sm);
}
</style>
