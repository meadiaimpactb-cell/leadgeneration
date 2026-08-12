<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MediaPicker from '@/Components/admin/MediaPicker.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The images chosen for one slot, as pictures rather than as a button.
 *
 * This replaces a bare "upload file" control that showed nothing back. An
 * editor could not tell what a section held without opening the site in
 * another tab, which meant the panel and the page were two sources of truth
 * for the same question.
 *
 * Purely about arrangement: it emits the list it wants and never saves. Where
 * that list is persisted differs between a section and a partner record, and
 * this component works in both because it does not know which it is in.
 */
const props = defineProps({
    /** The chosen images, in order: [{ id, thumb, url, name, translations }] */
    modelValue: { type: Array, default: () => [] },
    /** 1 for a single-image slot, 0 for as many as they like. */
    limit: { type: Number, default: 0 },
    label: { type: String, default: null },
    /** Shown under the grid; the caller explains what this slot is for. */
    hint: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useTranslation();

const locale = computed(() => usePage().props.locale ?? 'ar');

const picking = ref(false);
/** Set when the picker was opened by "replace" rather than by "add". */
const replacingAt = ref(null);
const dragging = ref(null);

const single = computed(() => props.limit === 1);
const items = computed(() => props.modelValue ?? []);
const full = computed(() => single.value && items.value.length >= 1);

function openAdd() {
    replacingAt.value = null;
    picking.value = true;
}

function openReplace(index) {
    replacingAt.value = index;
    picking.value = true;
}

/**
 * A replacement takes the position of the image it replaced.
 *
 * Appending it and leaving the old one to be deleted separately is the
 * behaviour that makes people reorder a gallery twice — once to put the new
 * image where the old one was, and once more after they notice the old one is
 * still there.
 */
function onInsert(chosen) {
    if (chosen.length === 0) return;

    if (replacingAt.value !== null) {
        const next = [...items.value];
        next.splice(replacingAt.value, 1, chosen[0]);
        replacingAt.value = null;
        emit('update:modelValue', dedupe(next));

        return;
    }

    if (single.value) {
        emit('update:modelValue', [chosen[0]]);

        return;
    }

    emit('update:modelValue', dedupe([...items.value, ...chosen]));
}

/** The database refuses a repeat; this keeps the screen from asking for one. */
function dedupe(list) {
    const seen = new Set();

    return list.filter((item) => {
        if (seen.has(item.id)) return false;
        seen.add(item.id);

        return true;
    });
}

function remove(index) {
    const next = [...items.value];
    next.splice(index, 1);
    emit('update:modelValue', next);
}

// ---- ordering --------------------------------------------------------
// Drag for the mouse, buttons for everything else. Not a fallback: §10.8
// requires full keyboard operation, and drag-and-drop alone has none.

function move(index, delta) {
    const next = index + delta;
    if (next < 0 || next >= items.value.length) return;

    const list = [...items.value];
    [list[index], list[next]] = [list[next], list[index]];
    emit('update:modelValue', list);
}

function onDrop(index) {
    if (dragging.value === null || dragging.value === index) return;

    const list = [...items.value];
    const [moved] = list.splice(dragging.value, 1);
    list.splice(index, 0, moved);
    dragging.value = null;
    emit('update:modelValue', list);
}
</script>

<template>
    <div class="slot">
        <p v-if="label" class="slot__label">{{ label }}</p>

        <ul v-if="items.length" class="slot__grid">
            <li
                v-for="(item, index) in items"
                :key="item.id"
                class="slot__cell"
                :draggable="!single"
                @dragstart="dragging = index"
                @dragover.prevent
                @drop.prevent="onDrop(index)"
            >
                <img :src="item.thumb ?? item.url" :alt="item.translations?.[locale]?.alt_text ?? ''" />

                <div class="slot__tools">
                    <button type="button" class="btn btn--ghost" @click="openReplace(index)">
                        {{ t('admin.media_replace') }}
                    </button>
                    <button type="button" class="btn btn--ghost danger" @click="remove(index)">
                        {{ t('admin.media_remove') }}
                    </button>
                </div>

                <div v-if="!single" class="slot__order">
                    <button
                        type="button"
                        class="btn btn--ghost"
                        :disabled="index === 0"
                        :aria-label="t('admin.move_up')"
                        @click="move(index, -1)"
                    >
                        ↑
                    </button>
                    <button
                        type="button"
                        class="btn btn--ghost"
                        :disabled="index === items.length - 1"
                        :aria-label="t('admin.move_down')"
                        @click="move(index, 1)"
                    >
                        ↓
                    </button>
                </div>
            </li>
        </ul>

        <button v-if="!full" type="button" class="btn btn--ghost slot__add" @click="openAdd">
            {{ single ? t('admin.media_add_image') : t('admin.media_add_images') }}
        </button>

        <p v-if="hint" class="slot__hint">{{ hint }}</p>
        <p v-else-if="items.length > 1" class="slot__hint">{{ t('admin.media_reorder_hint') }}</p>
        <p v-if="items.length" class="slot__hint">{{ t('admin.media_remove_hint') }}</p>

        <MediaPicker
            :open="picking"
            :limit="replacingAt !== null ? 1 : limit"
            :selected="replacingAt !== null ? [] : items.map((i) => i.id)"
            @close="picking = false"
            @insert="onInsert"
        />
    </div>
</template>

<style scoped>
.slot {
    margin-block-end: var(--s-4);
}

.slot__label {
    margin-block-end: var(--s-2);
    font-size: var(--fs-sm);
    color: var(--muted);
}

.slot__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: var(--s-3);
    margin: 0 0 var(--s-3);
    padding: 0;
    list-style: none;
}

.slot__cell {
    display: grid;
    gap: var(--s-2);
    padding: var(--s-2);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.slot__cell img {
    inline-size: 100%;
    block-size: 120px;
    /* `contain` for the same reason as the picker: a cropped logo is not a
       recognisable logo. */
    object-fit: contain;
}

.slot__tools,
.slot__order {
    display: flex;
    gap: var(--s-1);
}

.slot__hint {
    margin-block-start: var(--s-1);
    font-size: var(--fs-xs);
    color: var(--muted);
}
</style>
