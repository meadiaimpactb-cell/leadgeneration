<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import BilingualFields from '@/Components/admin/BilingualFields.vue';
import MediaSlot from '@/Components/admin/MediaSlot.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The section builder (§9.1).
 *
 * This is the screen that makes the site dynamic: add a section, reorder it,
 * fill its copy in both languages, upload its image, edit its items.
 *
 * Reordering is native drag-and-drop plus up/down buttons. The buttons are not
 * a fallback afterthought: drag-and-drop alone is unusable with a keyboard or
 * a screen reader, and §10.8 requires full keyboard operation.
 *
 * Images and repeatable items are real form controls rather than raw JSON —
 * §9.1 says the panel must be usable with no technical help, and asking an
 * editor to hand-write a file path inside JSON does not meet that.
 */
const props = defineProps({
    ownerType: { type: String, required: true },
    ownerId: { type: Number, required: true },
    ownerLabel: { type: String, default: null },
    sections: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const FIELDS = {
    heading: 'text',
    subheading: 'text',
    body: 'richtext',
    cta_label: 'text',
    cta_url: 'text',
};

/**
 * Which repeatable items each section type takes, and the fields of each item.
 * This is what turns "edit raw JSON" into a real form.
 */
const ITEM_SCHEMAS = {
    cards: [
        { key: 'icon', label: 'icon' },
        { key: 'title', label: 'title' },
        { key: 'body', label: 'body', type: 'textarea' },
    ],
    accordion: [
        { key: 'question', label: 'question' },
        { key: 'answer', label: 'answer', type: 'textarea' },
    ],
    timeline: [
        { key: 'year', label: 'year' },
        { key: 'title', label: 'title' },
        { key: 'body', label: 'body', type: 'textarea' },
    ],
};

/** Section types that take a single illustration. */
const IMAGE_TYPES = ['hero', 'media_split', 'testimonial'];

/**
 * Section types that take a set of images.
 *
 * `logos` is deliberately absent: a logos section renders the partners table,
 * not its own pictures, so its images are chosen on the partners screen. Same
 * for `story_carousel`. Offering a gallery here that the page would ignore is
 * worse than offering nothing.
 */
const GALLERY_TYPES = ['gallery'];

const open = ref(props.sections.length ? props.sections[0].id : null);
const dragging = ref(null);

const addForm = useForm({ type: props.types[0] ?? 'rich_text' });

function addSection() {
    addForm.post(`/admin/sections/${props.ownerType}/${props.ownerId}`, { preserveScroll: true });
}

function saveSection(section) {
    router.patch(
        `/admin/sections/${section.id}`,
        {
            is_active: section.isActive,
            settings: section.settings,
            translations: section.translations,
        },
        { preserveScroll: true }
    );
}

function removeSection(section) {
    if (!confirm(t('admin.confirm_delete'))) return;
    router.delete(`/admin/sections/${section.id}`, { preserveScroll: true });
}

function persistOrder(order) {
    router.post(
        `/admin/sections/${props.ownerType}/${props.ownerId}/reorder`,
        { order },
        { preserveScroll: true }
    );
}

function move(index, delta) {
    const next = index + delta;
    if (next < 0 || next >= props.sections.length) return;

    const ids = props.sections.map((s) => s.id);
    [ids[index], ids[next]] = [ids[next], ids[index]];
    persistOrder(ids);
}

function onDrop(index) {
    if (dragging.value === null || dragging.value === index) return;

    const ids = props.sections.map((s) => s.id);
    const [moved] = ids.splice(dragging.value, 1);
    ids.splice(index, 0, moved);
    dragging.value = null;
    persistOrder(ids);
}

// ---- repeatable items ------------------------------------------------

function itemSchema(section) {
    return ITEM_SCHEMAS[section.type] ?? null;
}

function items(section) {
    section.settings = section.settings ?? {};
    section.settings.items = section.settings.items ?? [];

    return section.settings.items;
}

function addItem(section) {
    const blank = {};
    itemSchema(section).forEach((f) => {
        blank[f.key] = '';
    });
    items(section).push(blank);
}

function removeItem(section, index) {
    items(section).splice(index, 1);
}

function moveItem(section, index, delta) {
    const list = items(section);
    const next = index + delta;
    if (next < 0 || next >= list.length) return;
    [list[index], list[next]] = [list[next], list[index]];
}

// ---- media -----------------------------------------------------------

/**
 * The chosen images for a slot, saved the moment they change.
 *
 * Saved on the spot rather than with the section's Save button because
 * choosing an image already felt like a completed action before this screen
 * existed — the upload button saved immediately — and making it suddenly
 * require a second, different Save is how images go missing.
 *
 * The whole arrangement is sent every time: insert, replace, remove and
 * reorder are one operation as far as the server is concerned, so the panel
 * cannot end up with an order the database disagrees with.
 */
function setMedia(section, collection, items) {
    section.media = section.media ?? {};
    section.media[collection] = items;

    router.post(
        `/admin/sections/${section.id}/media`,
        { collection, media: items.map((i) => i.id) },
        { preserveScroll: true, preserveState: true }
    );
}

// ---- advanced --------------------------------------------------------

/** Anything the typed controls above do not cover stays editable as JSON. */
function settingsText(section) {
    return JSON.stringify(section.settings ?? {}, null, 2);
}

function updateSettings(section, text) {
    try {
        section.settings = text.trim() === '' ? {} : JSON.parse(text);
        section.settingsError = null;
    } catch {
        section.settingsError = 'JSON';
    }
}
</script>

<template>
    <AdminLayout :title="`${t('admin.sections')} — ${ownerLabel ?? ''}`">
        <Panel :title="t('admin.add_section')" :hint="t('admin.order_hint')">
            <form class="add" @submit.prevent="addSection">
                <Field
                    v-model="addForm.type"
                    :label="t('admin.add_section')"
                    type="select"
                    :options="types.map((ty) => ({ value: ty, label: ty }))"
                />
                <button class="btn btn--cta" type="submit" :disabled="addForm.processing">
                    {{ t('admin.create') }}
                </button>
            </form>
        </Panel>

        <p v-if="!sections.length" class="empty">{{ t('admin.no_records') }}</p>

        <ol class="list">
            <li
                v-for="(section, index) in sections"
                :key="section.id"
                class="item"
                draggable="true"
                @dragstart="dragging = index"
                @dragover.prevent
                @drop.prevent="onDrop(index)"
            >
                <header class="item__head">
                    <button
                        class="item__toggle"
                        type="button"
                        :aria-expanded="open === section.id"
                        @click="open = open === section.id ? null : section.id"
                    >
                        <span class="item__type latin">{{ section.type }}</span>
                        <span v-if="!section.isActive" class="chip">{{ t('admin.inactive') }}</span>
                    </button>

                    <div class="item__tools">
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
                            :disabled="index === sections.length - 1"
                            @click="move(index, 1)"
                        >↓</button>
                        <button class="btn btn--ghost danger" type="button" @click="removeSection(section)">
                            {{ t('admin.delete') }}
                        </button>
                    </div>
                </header>

                <div v-if="open === section.id" class="item__body">
                    <Field v-model="section.isActive" :label="t('admin.active')" type="checkbox" />

                    <BilingualFields
                        v-model="section.translations"
                        :locales="locales"
                        :fields="FIELDS"
                    />

                    <!-- Chosen from the library and shown as pictures, so the
                         panel answers "what is on this page" by itself. -->
                    <MediaSlot
                        v-if="IMAGE_TYPES.includes(section.type)"
                        :model-value="section.media?.image ?? []"
                        :limit="1"
                        :label="t('admin.section_image')"
                        @update:model-value="(v) => setMedia(section, 'image', v)"
                    />

                    <MediaSlot
                        v-if="GALLERY_TYPES.includes(section.type)"
                        :model-value="section.media?.gallery ?? []"
                        :label="t('admin.section_gallery')"
                        @update:model-value="(v) => setMedia(section, 'gallery', v)"
                    />

                    <!-- Repeatable items as a form, not as JSON. -->
                    <div v-if="itemSchema(section)" class="items">
                        <div class="items__head">
                            <p class="media__label">{{ t('admin.section_items') }}</p>
                            <button class="btn btn--ghost" type="button" @click="addItem(section)">
                                {{ t('admin.add_item') }}
                            </button>
                        </div>

                        <div v-for="(item, i) in items(section)" :key="i" class="items__row">
                            <Field
                                v-for="f in itemSchema(section)"
                                :key="f.key"
                                v-model="item[f.key]"
                                :label="f.label"
                                :type="f.type ?? 'text'"
                                :rows="2"
                            />

                            <div class="items__tools">
                                <button
                                    class="btn btn--ghost"
                                    type="button"
                                    :aria-label="t('admin.move_up')"
                                    :disabled="i === 0"
                                    @click="moveItem(section, i, -1)"
                                >↑</button>
                                <button
                                    class="btn btn--ghost"
                                    type="button"
                                    :aria-label="t('admin.move_down')"
                                    :disabled="i === items(section).length - 1"
                                    @click="moveItem(section, i, 1)"
                                >↓</button>
                                <button class="btn btn--ghost danger" type="button" @click="removeItem(section, i)">
                                    {{ t('admin.delete') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <details class="advanced">
                        <summary>{{ t('admin.advanced_json') }}</summary>
                        <Field
                            label="settings"
                            type="textarea"
                            dir="ltr"
                            :model-value="settingsText(section)"
                            :error="section.settingsError"
                            @update:model-value="(v) => updateSettings(section, v)"
                        />
                    </details>

                    <button class="btn btn--cta" type="button" @click="saveSection(section)">
                        {{ t('admin.save') }}
                    </button>
                </div>
            </li>
        </ol>
    </AdminLayout>
</template>

<style scoped>
.add {
    display: flex;
    gap: var(--s-3);
    align-items: end;
    flex-wrap: wrap;
}

.list {
    margin-block-start: var(--s-5);
    display: flex;
    flex-direction: column;
    gap: var(--s-3);
}

.item {
    background: var(--paper);
    border-radius: var(--r-md);
    box-shadow: inset 0 0 0 1px var(--hairline);
}

.item__head {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-3) var(--s-4);
}

.item__toggle {
    display: flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    font-weight: 600;
    color: var(--navy-900);
}

.item__type {
    font-size: var(--fs-sm);
}

.item__tools {
    display: flex;
    gap: var(--s-1);
    margin-inline-start: auto;
}

.item__tools .btn {
    min-inline-size: 44px;
    min-block-size: 44px;
    padding-inline: var(--s-2);
    font-size: var(--fs-sm);
}

.item__body {
    display: flex;
    flex-direction: column;
    gap: var(--s-5);
    padding: var(--s-4);
    border-block-start: 1px solid var(--hairline);
}

.chip {
    padding: var(--s-1) var(--s-2);
    border-radius: var(--r-sm);
    font-size: var(--fs-xs);
    background: var(--gold-100);
    color: var(--action-600);
}

.danger {
    color: var(--action-600);
}

.media__label {
    font-size: var(--fs-sm);
    font-weight: 600;
    margin-block-end: var(--s-3);
}

.items__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
}

.items__row {
    display: grid;
    gap: var(--s-3);
    grid-template-columns: 1fr;
    padding: var(--s-3);
    margin-block-start: var(--s-3);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.items__tools {
    display: flex;
    gap: var(--s-1);
}

.items__tools .btn {
    min-inline-size: 44px;
    min-block-size: 44px;
    padding-inline: var(--s-2);
    font-size: var(--fs-xs);
}

.advanced summary {
    cursor: pointer;
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--text-muted);
    min-block-size: 44px;
    display: flex;
    align-items: center;
}

.empty {
    margin-block-start: var(--s-5);
    color: var(--text-muted);
    font-size: var(--fs-sm);
}

@media (min-width: 1024px) {
    .items__row {
        grid-template-columns: repeat(3, 1fr) auto;
        align-items: end;
    }
}
</style>
