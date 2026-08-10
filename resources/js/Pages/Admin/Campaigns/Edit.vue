<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Panel from '@/Components/admin/Panel.vue';
import Field from '@/Components/admin/Field.vue';
import BilingualFields from '@/Components/admin/BilingualFields.vue';
import { useTranslation } from '@/Composables/useTranslation';

/**
 * Step 1 of the campaign wizard (§9.1).
 *
 * Creating a campaign scaffolds its whole page structure server-side and
 * lands the user straight in the section builder — step 2 — so a landing page
 * exists from the first save rather than after assembling it by hand.
 */
const props = defineProps({
    campaign: { type: Object, default: null },
    locales: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const FIELDS = {
    title: 'text',
    meta_title: 'text',
    meta_description: 'textarea',
};

function blankTranslations() {
    return Object.fromEntries(
        props.locales.map((l) => [l, Object.fromEntries(Object.keys(FIELDS).map((f) => [f, '']))])
    );
}

const form = useForm({
    slug: props.campaign?.slug ?? '',
    is_active: props.campaign?.is_active ?? false,
    starts_at: props.campaign?.starts_at ?? '',
    ends_at: props.campaign?.ends_at ?? '',
    default_utm_source: props.campaign?.default_utm_source ?? '',
    default_utm_medium: props.campaign?.default_utm_medium ?? '',
    default_utm_campaign: props.campaign?.default_utm_campaign ?? '',
    translations: props.campaign?.translations ?? blankTranslations(),
});

function submit() {
    if (props.campaign) {
        form.patch(`/admin/campaigns/${props.campaign.id}`, { preserveScroll: true });
    } else {
        form.post('/admin/campaigns');
    }
}
</script>

<template>
    <AdminLayout :title="campaign ? campaign.slug : t('admin.campaign_wizard')">
        <ol class="steps">
            <li class="steps__item is-current">{{ t('admin.campaign_step_1') }}</li>
            <li class="steps__item">{{ t('admin.campaign_step_2') }}</li>
            <li class="steps__item">{{ t('admin.campaign_step_3') }}</li>
        </ol>

        <form @submit.prevent="submit">
            <Panel :title="t('admin.shared_fields')">
                <template #actions>
                    <a v-if="campaign" class="btn btn--ghost" :href="campaign.previewUrl" target="_blank" rel="noopener">
                        {{ t('admin.preview') }}
                    </a>
                    <Link
                        v-if="campaign"
                        :href="`/admin/sections/campaign/${campaign.id}`"
                        class="btn btn--secondary"
                    >
                        {{ t('admin.sections') }}
                    </Link>
                </template>

                <div class="grid">
                    <Field v-model="form.slug" label="slug" type="slug" dir="ltr" :error="form.errors.slug" required />
                    <Field v-model="form.starts_at" label="starts_at" type="date" :error="form.errors.starts_at" />
                    <Field v-model="form.ends_at" label="ends_at" type="date" :error="form.errors.ends_at" />
                    <Field v-model="form.default_utm_source" label="utm_source" dir="ltr" />
                    <Field v-model="form.default_utm_medium" label="utm_medium" dir="ltr" />
                    <Field v-model="form.default_utm_campaign" label="utm_campaign" dir="ltr" />
                    <Field v-model="form.is_active" :label="t('admin.active')" type="checkbox" />
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

            <div class="bar">
                <button class="btn btn--cta" type="submit" :disabled="form.processing">
                    {{ form.processing ? t('admin.saving') : t('admin.save') }}
                </button>
                <Link href="/admin/campaigns" class="btn btn--ghost">{{ t('admin.cancel') }}</Link>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.steps {
    display: flex;
    flex-wrap: wrap;
    gap: var(--s-2);
    margin-block-end: var(--s-5);
}

.steps__item {
    padding: var(--s-2) var(--s-4);
    border-radius: var(--r-pill);
    background: var(--paper);
    box-shadow: inset 0 0 0 1px var(--hairline);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--text-muted);
}

.steps__item.is-current {
    background: var(--navy-900);
    color: #fff;
    box-shadow: none;
}

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

@media (min-width: 640px) {
    .grid {
        grid-template-columns: repeat(3, 1fr);
        align-items: end;
    }
}
</style>
