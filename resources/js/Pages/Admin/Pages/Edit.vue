<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import BilingualFields from '@/Components/admin/BilingualFields.vue';
import SnippetPreview from '@/Components/admin/SnippetPreview.vue';
import { useTranslation } from '@/Composables/useTranslation';

const props = defineProps({
    page: { type: Object, default: null },
    locales: { type: Array, default: () => [] },
    siteNames: { type: Object, default: () => ({}) },
    baseUrl: { type: String, default: '' },
});

const { t } = useTranslation();

const FIELDS = {
    title: 'text',
    subtitle: 'text',
    excerpt: 'textarea',
    meta_title: 'text',
    meta_description: 'textarea',
    keywords: 'text',
};

function blankTranslations() {
    return Object.fromEntries(
        props.locales.map((l) => [l, Object.fromEntries(Object.keys(FIELDS).map((f) => [f, '']))])
    );
}

const form = useForm({
    slug: props.page?.slug ?? '',
    template: props.page?.template ?? 'default',
    is_indexable: props.page?.is_indexable ?? true,
    translations: props.page?.translations ?? blankTranslations(),
});

function submit() {
    if (props.page) {
        form.patch(`/admin/pages/${props.page.id}`, { preserveScroll: true });
    } else {
        form.post('/admin/pages');
    }
}
</script>

<template>
    <AdminLayout :title="page ? page.slug : t('admin.create')">
        <form @submit.prevent="submit">
            <Panel :title="t('admin.shared_fields')">
                <template #actions>
                    <a v-if="page" class="btn btn--ghost" :href="page.previewUrl" target="_blank" rel="noopener">
                        {{ t('admin.preview') }}
                    </a>
                    <Link
                        v-if="page"
                        :href="`/admin/sections/page/${page.id}`"
                        class="btn btn--secondary"
                    >
                        {{ t('admin.sections') }}
                    </Link>
                </template>

                <div class="grid">
                    <Field
                        v-model="form.slug"
                        label="slug"
                        type="slug"
                        dir="ltr"
                        :error="form.errors.slug"
                        required
                    />
                    <Field
                        v-model="form.template"
                        label="template"
                        dir="ltr"
                        :error="form.errors.template"
                    />
                    <Field
                        v-model="form.is_indexable"
                        :label="t('admin.indexable')"
                        type="checkbox"
                    />
                </div>
            </Panel>

            <Panel :title="t('admin.per_locale')">
                <BilingualFields
                    v-model="form.translations"
                    :locales="locales"
                    :fields="FIELDS"
                    :errors="form.errors"
                />
            </Panel>

            <!-- Placed after the fields, not before: it is the consequence of
                 what was just typed, and it updates as it is typed. -->
            <Panel :title="t('admin.snippet.panel')">
                <p class="snippet-intro">{{ t('admin.snippet.intro') }}</p>

                <div class="snippets">
                    <SnippetPreview
                        v-for="locale in locales"
                        :key="locale"
                        :locale="locale"
                        :title="form.translations[locale]?.title"
                        :meta-title="form.translations[locale]?.meta_title"
                        :meta-description="form.translations[locale]?.meta_description"
                        :slug="form.slug"
                        :site-name="siteNames[locale] ?? ''"
                        :base-url="baseUrl"
                        :indexable="Boolean(form.is_indexable)"
                    />
                </div>
            </Panel>

            <div class="bar">
                <button class="btn btn--cta" type="submit" :disabled="form.processing">
                    {{ form.processing ? t('admin.saving') : t('admin.save') }}
                </button>
                <Link href="/admin/pages" class="btn btn--ghost">{{ t('admin.cancel') }}</Link>
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

.snippet-intro {
    margin-block-end: var(--s-4);
    color: var(--ink-600);
    line-height: var(--lh-body);
}

/* Side by side once there is room: the two languages are compared, not read
   one after the other. */
.snippets {
    display: grid;
    gap: var(--s-5);
    grid-template-columns: 1fr;
}

@media (min-width: 900px) {
    .snippets {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.bar {
    display: flex;
    gap: var(--s-3);
    margin-block-start: var(--s-5);
}

@media (min-width: 640px) {
    .grid {
        grid-template-columns: repeat(3, 1fr);
        align-items: end;
    }
}
</style>
