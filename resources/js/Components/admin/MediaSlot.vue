<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MediaPicker from '@/Components/admin/MediaPicker.vue';
import NavIcon from '@/Components/admin/NavIcon.vue';
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

/** A clip cannot be drawn with <img>; the browser paints its first frame. */
function isVideo(item) {
    return String(item?.mime ?? '').startsWith('video/');
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
                <video v-if="isVideo(item)" :src="item.url" muted playsinline preload="metadata" />
                <img
                    v-else
                    :src="item.thumb ?? item.url"
                    :alt="item.translations?.[locale]?.alt_text ?? ''"
                />

                <!--
                    Icons, not words. «استبدال» and «إزالة من القسم» wrapped to
                    three lines inside a 160px card and pushed the picture out
                    of shape. The label survives as aria-label and title, so a
                    screen reader and a hesitating cursor both still get it.
                -->
                <div class="slot__tools">
                    <button
                        type="button"
                        class="icon-btn"
                        :aria-label="t('admin.media_replace')"
                        :title="t('admin.media_replace')"
                        @click="openReplace(index)"
                    >
                        <NavIcon name="swap" :size="16" :muted="false" />
                    </button>

                    <button
                        type="button"
                        class="icon-btn danger"
                        :aria-label="t('admin.media_remove')"
                        :title="t('admin.media_remove')"
                        @click="remove(index)"
                    >
                        <NavIcon name="trash" :size="16" :muted="false" />
                    </button>

                    <template v-if="!single">
                        <button
                            type="button"
                            class="icon-btn"
                            :disabled="index === 0"
                            :aria-label="t('admin.move_up')"
                            :title="t('admin.move_up')"
                            @click="move(index, -1)"
                        >
                            ↑
                        </button>
                        <button
                            type="button"
                            class="icon-btn"
                            :disabled="index === items.length - 1"
                            :aria-label="t('admin.move_down')"
                            :title="t('admin.move_down')"
                            @click="move(index, 1)"
                        >
                            ↓
                        </button>
                    </template>
                </div>
            </li>
        </ul>

        <button v-if="!full" type="button" class="slot__add" @click="openAdd">
            <NavIcon name="media" :size="18" :muted="false" />
            <span>{{ single ? t('admin.media_add_image') : t('admin.media_add_images') }}</span>
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

.slot__cell img,
.slot__cell video {
    inline-size: 100%;
    block-size: 120px;
    /* `contain` for the same reason as the picker: a cropped logo is not a
       recognisable logo. */
    object-fit: contain;
}

/*
 * Under the picture, always visible — the original arrangement, with icons
 * where the words were.
 *
 * Two wrong turns got here. Words under the thumbnail wrapped to three lines
 * and squashed the picture; icons floated over the picture covered the thing
 * the editor is trying to look at. The layout was never the problem — the
 * labels were too long for it. Icons in the same row the words occupied cost
 * the picture nothing and hide nothing.
 */
.slot__tools {
    display: flex;
    justify-content: center;
    gap: var(--s-1);
}

.icon-btn {
    display: grid;
    place-items: center;
    /* §10.8: a touch target is 44px even when the glyph inside is 16. */
    inline-size: 44px;
    block-size: 44px;
    padding: 0;
    border: 0;
    border-radius: var(--r-sm);
    background: none;
    color: var(--ink);
    font: inherit;
    cursor: pointer;
}

.icon-btn:hover {
    background: var(--navy-100);
}

.icon-btn:disabled {
    opacity: 0.35;
    cursor: default;
}

.icon-btn.danger {
    color: var(--action-600);
}

/*
 * The way images get added, so it has to look like it.
 *
 * It was a ghost button: dark text on the panel's own background, sitting
 * under a row of photographs. An operator looking for "how do I add a
 * picture" reads it as a caption. This is the one action in the block, and
 * the only one drawn as a button.
 */
.slot__add {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    padding-inline: var(--s-4);
    border: 1px dashed var(--action-600);
    border-radius: var(--r-sm);
    background: var(--gold-100);
    color: var(--action-600);
    font: inherit;
    font-weight: 600;
    cursor: pointer;
}

.slot__add:hover {
    background: var(--paper);
    border-style: solid;
}

.slot__hint {
    margin-block-start: var(--s-1);
    font-size: var(--fs-xs);
    color: var(--muted);
}
</style>
