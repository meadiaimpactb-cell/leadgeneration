<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import BilingualFields from '@/Components/admin/BilingualFields.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * The edit screen shared by every content entity (§9.1).
 *
 * The form draws itself from `meta`, which the server builds from
 * ContentRegistry — so adding a field to an entity is a one-line change there,
 * not a new Vue file.
 */
const props = defineProps({
    entity: { type: String, required: true },
    record: { type: Object, default: null },
    meta: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    locales: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const uploading = ref(null);

function blankTranslations() {
    return Object.fromEntries(
        props.locales.map((l) => [l, Object.fromEntries(Object.keys(props.meta.fields).map((f) => [f, '']))])
    );
}

function blankAttributes() {
    return Object.fromEntries(Object.keys(props.meta.attributes).map((a) => [a, '']));
}

const form = useForm({
    active: props.record?.active ?? true,
    attributes: props.record?.attributes ?? blankAttributes(),
    translations: props.record?.translations ?? blankTranslations(),
});

function inputType(type) {
    if (type === 'number') return 'number';
    if (type === 'url') return 'url';
    if (type.startsWith('relation:') || type.startsWith('enum:')) return 'select';
    return 'text';
}

function optionsFor(name, type) {
    if (type.startsWith('relation:')) {
        return props.options[name] ?? [];
    }

    if (type.startsWith('enum:')) {
        return type
            .slice('enum:'.length)
            .split(',')
            .map((v) => ({ value: v, label: v }));
    }

    return [];
}

function submit() {
    if (props.record) {
        form.patch(`/admin/content/${props.entity}/${props.record.id}`, { preserveScroll: true });
    } else {
        form.post(`/admin/content/${props.entity}`);
    }
}

function upload(collection, event) {
    const file = event.target.files?.[0];
    if (!file || !props.record) return;

    uploading.value = collection;

    router.post(
        '/admin/media',
        { entity: props.entity, id: props.record.id, collection, file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                uploading.value = null;
                event.target.value = '';
            },
        }
    );
}

function removeMedia(id) {
    if (!confirm(t('admin.confirm_delete'))) return;
    router.delete(`/admin/media/${id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="record ? (record.attributes.slug ?? record.attributes.name ?? t('admin.edit')) : t('admin.create')">
        <form @submit.prevent="submit">
            <Panel :title="t('admin.shared_fields')">
                <div class="grid">
                    <Field
                        v-for="(type, name) in meta.attributes"
                        :key="name"
                        v-model="form.attributes[name]"
                        :label="name.replace(/_/g, ' ')"
                        :type="inputType(type)"
                        :options="optionsFor(name, type)"
                        :dir="type === 'slug' || type === 'url' ? 'ltr' : null"
                        :error="form.errors[`attributes.${name}`]"
                        :required="type === 'slug'"
                    />

                    <Field v-model="form.active" :label="t('admin.active')" type="checkbox" />
                </div>
            </Panel>

            <Panel :title="t('admin.per_locale')">
                <BilingualFields
                    v-model="form.translations"
                    :locales="locales"
                    :fields="meta.fields"
                    :errors="form.errors"
                />
            </Panel>

            <div class="bar">
                <button class="btn btn--cta" type="submit" :disabled="form.processing">
                    {{ form.processing ? t('admin.saving') : t('admin.save') }}
                </button>
                <Link :href="`/admin/content/${entity}`" class="btn btn--ghost">
                    {{ t('admin.cancel') }}
                </Link>
            </div>
        </form>

        <!-- Media is uploaded against a saved record, so it only appears once
             the record exists. -->
        <Panel v-if="record && meta.media.length" :title="t('admin.media')">
            <div v-for="collection in meta.media" :key="collection" class="media">
                <p class="media__label latin">{{ collection }}</p>

                <ul v-if="record.media[collection]?.length" class="media__list">
                    <li v-for="item in record.media[collection]" :key="item.id" class="media__item">
                        <img :src="item.url" :alt="item.name" class="media__thumb" />
                        <button class="btn btn--ghost danger" type="button" @click="removeMedia(item.id)">
                            {{ t('admin.delete') }}
                        </button>
                    </li>
                </ul>

                <label class="media__upload">
                    <span>{{ uploading === collection ? t('admin.saving') : t('admin.upload') }}</span>
                    <input type="file" @change="(e) => upload(collection, e)" />
                </label>
            </div>
        </Panel>
    </AdminLayout>
</template>

<style scoped>
.grid {
    display: grid;
    gap: var(--s-4);
    grid-template-columns: 1fr;
}

.bar {
    display: flex;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

.media + .media {
    margin-block-start: var(--s-5);
    padding-block-start: var(--s-5);
    border-block-start: 1px solid var(--hairline);
}

.media__label {
    font-size: var(--fs-sm);
    font-weight: 600;
    margin-block-end: var(--s-3);
}

.media__list {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-3);
    margin-block-end: var(--s-3);
}

.media__item {
    inline-size: 160px;
}

/*
 * `contain`, not `cover`. A preview exists to answer "is this the file I
 * meant?", and `cover` cropped every non-square asset to its middle — a wide
 * logo showed as three letters, which reads as a broken upload rather than a
 * cropped thumbnail. Padding keeps the mark off the border, and the light
 * ground gives transparent logos something to sit on.
 */
.media__thumb {
    inline-size: 160px;
    block-size: 120px;
    object-fit: contain;
    padding: var(--s-2);
    border: 1px solid var(--hairline);
    border-radius: var(--r-sm);
    background: var(--paper-alt);
}

.media__upload {
    /* Contains the visually-hidden file input below; without it the input is
       positioned against the page instead of the button. */
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    min-block-size: 44px;
    padding-inline: var(--s-4);
    border-radius: var(--r-sm);
    background: var(--navy-100);
    font-size: var(--fs-sm);
    font-weight: 600;
    cursor: pointer;
}

.media__upload input {
    inline-size: 1px;
    block-size: 1px;
    opacity: 0;
    position: absolute;
}

.danger {
    color: var(--action-600);
}

@media (min-width: 640px) {
    .grid {
        grid-template-columns: repeat(2, 1fr);
        align-items: end;
    }
}
</style>
